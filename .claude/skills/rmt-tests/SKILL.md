---
name: rmt-tests
description: Padrões de teste do rmt-system — PHPUnit class-style, RefreshDatabase, fakes, asserções de API JSON, regressão IDOR, contagem de queries e comando correto de execução.
user-invocable: false
---

# rmt-system — Testes

## Quando usar

- Toda vez que escrever ou alterar arquivo em `tests/`
- Adicionar cobertura para feature nova ou bug corrigido
- Decidir entre Feature e Unit, ou se algo merece teste

## Stack

- PHPUnit class style (sem Pest puro) — manter consistência com os testes existentes
- Trait padrão: `Illuminate\Foundation\Testing\RefreshDatabase`
- Suítes: `tests/Feature/` (HTTP/integração) e `tests/Unit/` (regra isolada)
- Referência real: `tests/Feature/HealthTest.php` — exemplo canônico de teste de API JSON no projeto
- Marketplace: `tests/Feature/Marketplace/EscrowFlowTest.php` (fluxo de escrow ponta-a-ponta: compra debita, dupla confirmação libera com taxa 5%, IDOR 404, papel errado 403, idempotência), `ListingTest.php`, `BoostTest.php` (boost via carteira, saldo insuficiente 422, IDOR 404, já-turbinado 422, featured ordenado por tier, grid floata intermediário/avançado), `tests/Feature/Auth/AuthTest.php`, e Unit `BoostTierTest.php` (preços/peso). Factories: `WalletFactory` (`->withBalance(cents)`), `ListingFactory`, `OrderFactory`, `BoostFactory` (`->tier()`, `->expired()`).

> **Rodar fora do Docker (host)**: o host não tem `pdo_sqlite` carregado por padrão e o `.env` aponta para MySQL (`mysql` não resolve no host). Para rodar local rápido:
> `DB_CONNECTION=sqlite DB_DATABASE=:memory: php -d extension=pdo_sqlite vendor/bin/phpunit`
> (use `vendor/bin/phpunit` direto — `php artisan test` re-spawna sem o `-d`). O caminho canônico continua sendo dentro do container (`docker compose exec -T app php artisan test`).
> **Nunca** rode `composer dump-autoload` dentro do container `app` com os workers Octane vivos — corrompe o autoloader em memória (erros tipo `Target class [config] does not exist`); use `octane:reload`/restart depois.

- Pagamentos PIX: `tests/Feature/Wallet/PixTopUpTest.php` — usa `Http::fake(['*/api/pix/cashIn' => ..., '*/api/transactions/*' => ...])` p/ o gateway PushinPay e `Queue::fake()` p/ asserir `ProcessPushinPayWebhookJob`. Cobre: gera QR, gateway falho → 503, escopo dono → 404, polling confirma+credita, `confirmPaid` idempotente (credita 1×), webhook token inválido → 404, webhook válido despacha job. Factory `PixChargeFactory` (`->paid()`). **Nunca** chamar a API real da PushinPay em teste — sempre `Http::fake`.

## Convenção de classe e nomenclatura

```php
namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class NomeFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_retorna_ok_quando_autenticado(): void
    {
        // ...
    }
}
```

Nome do método sempre `test_<o_que_acontece>_<condicao>` (snake_case, comportamento primeiro).

## Ambiente de teste (`phpunit.xml`)

Configurado em `phpunit.xml` — não precisa setar manualmente em cada teste:

- `APP_ENV=testing`
- `BCRYPT_ROUNDS=4` (acelera `Hash::make`)
- `BROADCAST_CONNECTION=null` (broadcast vira no-op)
- `CACHE_STORE=array` (cache em memória, descartado por teste)
- `DB_DATABASE=testing`
- `MAIL_MAILER=array` (e-mails capturados, não enviados)
- `QUEUE_CONNECTION=sync` (jobs rodam inline)
- `SESSION_DRIVER=array`
- `PULSE_ENABLED=false`, `TELESCOPE_ENABLED=false`, `NIGHTWATCH_ENABLED=false`

## Como rodar (container correto)

```bash
# Rodar toda a suíte
docker compose exec -T app php artisan test

# Filtrar por classe
docker compose exec -T app php artisan test --filter NomeTest

# Rodar diretório
docker compose exec -T app php artisan test tests/Feature/Auth

# Alternativa: PHPUnit direto
docker compose exec -T app vendor/bin/phpunit
```

**Container correto: serviço `app`** (conforme `compose.yaml`). O host não resolve o hostname `mysql` — sempre rodar dentro do container.

## Asserções de API JSON

rmt é API pura — todas as asserções de resposta usam a família JSON:

```php
// Status + corpo exato
$this->getJson('/api/health')
    ->assertOk()
    ->assertExactJson(['status' => 'ok']);

// Status + estrutura
$response->assertOk()
    ->assertJsonStructure(['data' => ['id', 'name', 'created_at']]);

// Status + conteúdo parcial
$response->assertOk()
    ->assertJson(['name' => 'Esperado']);

// Path específico no JSON
$response->assertJsonPath('data.0.status', 'active');

// Criação
$response->assertCreated()
    ->assertJson(['name' => $data['name']]);

// Validação falhou
$response->assertUnprocessable()
    ->assertJsonValidationErrors(['name']);

// 404 anti-enumeração
$response->assertNotFound();
```

## Tipos de teste e onde fazer

### Feature (HTTP/integração)

- Endpoint novo → teste em `tests/Feature/`
- IDOR/escopo (cross-owner, cross-tenant) → seguir o padrão de teste IDOR documentado neste skill
- Webhook → mockar HTTP externa, simular request com `withHeaders([...])` e body raw
- Auth/Sanctum → testar com token válido, token expirado, token sem ability

### Unit (regra isolada)

- Policy → testar unitariamente cada método (ver `tests/Unit/`)
- Service com lógica não-trivial sem dependência de HTTP/banco pesado
- Enum, value object, helper

## Fakes obrigatórios em Feature tests

### Storage (S3)

```php
Storage::fake('s3');
Storage::disk('s3')->buildTemporaryUrlsUsing(
    fn (string $path) => "http://fake-s3.test/{$path}"
);
```

`buildTemporaryUrlsUsing` é obrigatório — o driver fake não suporta URL temporária por padrão, e Resources que chamam `temporaryUrl()` falham sem ele.

### Queue, Mail, Notification, Bus, Event

```php
Queue::fake();        // jobs não rodam; asserir com Queue::assertPushed(...)
Mail::fake();         // e-mails capturados
Notification::fake();
Bus::fake();
Event::fake();        // usar com cuidado — global mascara listeners reais
```

`QUEUE_CONNECTION=sync` no `phpunit.xml` faz jobs rodarem inline. Escolha:
- Quer testar **que o job foi despachado** → `Queue::fake()`
- Quer testar **o efeito do job** → deixa `sync` agir + asserir estado final

### Mock de processo externo (binário indisponível)

Serviços que dependem de binário externo (ex.: Chrome headless) devem ser encapsulados em wrapper injetável e mockados via `$this->app->instance(...)`:

```php
$fake = $this->createMock(ExternalProcessWrapper::class);
$fake->method('render')->willReturn('fake-bytes');
$this->app->instance(ExternalProcessWrapper::class, $fake);
```

## Padrão IDOR test

Para todo endpoint sob hierarquia de owner:

1. Criar **dois owners** com **dois usuários** distintos
2. Para cada endpoint sub-recurso:
   - **Acesso legítimo** (todos os IDs corretos) → 200/201
   - **Cross-owner** (recurso de outro owner) → 404
   - **Cross-recurso** (filho de outro pai no mesmo owner) → 404
3. **Sempre 404, nunca 403** — anti-enumeração (regra rmt-security)

```php
public function test_cross_owner_returns_404(): void
{
    [$userA, $ownerA] = $this->createUserAndOwner();
    [$userB, $ownerB] = $this->createUserAndOwner();

    $resource = Resource::factory()->for($ownerA)->create();

    $this->actingAs($userB)
        ->getJson("/api/owners/{$ownerB->id}/resources/{$resource->id}")
        ->assertNotFound();
}
```

## Regressão de IDOR/escopo — o que cobrir

- Endpoint que aceita array de FKs filhos: teste que IDs de outro owner são descartados silenciosamente (CWE-639)
- Exemplo:
  ```php
  public function test_store_drops_ids_from_foreign_owner(): void
  {
      $foreignChild = Child::factory()->for($this->otherOwner)->create();
      $response = $this->actingAs($this->user)
          ->postJson("/api/parents/{$this->parent->id}", [
              'child_ids' => [$foreignChild->id],
          ])
          ->assertCreated();
      $this->assertEmpty($this->parent->fresh()->children);
  }
  ```

## Teste de contagem de queries constante

Para endpoints com agregação (ex.: relatórios, listagens com eager load):

```php
public function test_constant_query_count_for_listing(): void
{
    Resource::factory()->count(10)->for($this->owner)->create();

    DB::enableQueryLog();

    $this->actingAs($this->user)
        ->getJson("/api/owners/{$this->owner->id}/resources")
        ->assertOk();

    $count = count(DB::getQueryLog());
    $this->assertLessThanOrEqual(5, $count, "Esperado ≤5 queries, got {$count}");
}
```

Trava regressão N+1 — rodar antes e depois de adicionar eager loads.

## Factories

Criar factory junto com a feature — não usar `Model::create([...])` direto em testes Feature novos.

Estrutura de referência:
```php
class OwnerFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->words(3, true),
            'user_id' => User::factory(),
        ];
    }
}
```

## Testar Sanctum (autenticação API)

```php
// Usuário autenticado com token
$token = $user->createToken('test', ['resource:read'])->plainTextToken;
$this->withToken($token)->getJson('/api/resource')->assertOk();

// Sem autenticação
$this->getJson('/api/resource')->assertUnauthorized();

// Token sem ability
$tokenNoAbility = $user->createToken('test', [])->plainTextToken;
$this->withToken($tokenNoAbility)->postJson('/api/resource', $data)->assertForbidden();

// Alternativa sem token (actingAs — para testes que não precisam testar o token em si)
$this->actingAs($user, 'sanctum')->getJson('/api/resource')->assertOk();
```

## O que cobrir antes de encerrar tarefa

- **Endpoint novo**: 1 teste de happy path + 1 IDOR (se sub-recurso) + 1 de autorização negada (sem token/ability insuficiente)
- **Bug corrigido**: 1 teste de regressão que falharia sem o fix
- **Service novo com regra não-trivial**: 1 teste Unit por branch importante
- **Migration que muda forma de dados existentes**: validar idempotência rodando `php artisan migrate` duas vezes em ambiente de teste
- **Endpoint com agregação**: 1 teste de contagem de queries para travar regressão N+1

## O que não testar

- Getter/setter trivial de Eloquent (não há comportamento)
- Resource que apenas faz `'campo' => $this->campo` (testar via teste de endpoint, não isolado)
- Componente Vue (não há suíte frontend — smoke test manual)

## Não fazer

- Mockar banco em teste de regra de negócio que pretende **ler** dados — usar `RefreshDatabase` com banco real
- Esquecer `Storage::fake('s3')` em teste que toca arquivo (tenta chamar S3 real)
- Usar asserções de view PHP renderizada — rmt é API pura; usar `assertJson`/`assertExactJson`/`assertJsonStructure`
- Criar teste Feature que depende de `npm run build` — frontend não entra na suíte PHPUnit
- Pular `RefreshDatabase` em Feature test — banco residual quebra o próximo teste
- Rodar testes no host (fora do container) — hostname `mysql` não resolve
- Asserir contra `response()->json($model)` direto — endpoint deve retornar via Resource

## Referências rápidas

- Configuração: `phpunit.xml`
- Base class: `tests/TestCase.php`
- Exemplo canônico: `tests/Feature/HealthTest.php`
- Suítes: `tests/Feature/` e `tests/Unit/`
- Comando: `docker compose exec -T app php artisan test`
