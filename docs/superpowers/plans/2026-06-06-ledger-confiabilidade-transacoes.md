# Ledger de Confiabilidade — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Dar a toda transação financeira um hash de confiabilidade (HMAC-SHA256 encadeado por carteira) registrado num ledger append-only, com verificação por endpoint, recibo na UI e comando de auditoria.

**Architecture:** Tabela `ledger_entries` imutável; cada linha leva HMAC do payload canônico + `prev_hash` da linha anterior da mesma wallet; a cabeça da cadeia é ancorada em colunas novas na `wallets` (anti-truncamento). Um `LedgerService` stateless (Octane-safe) é injetado nos serviços de dinheiro e apenda a linha **dentro** da transação já existente, com a wallet já travada. Verificação via endpoint + comando artisan.

**Tech Stack:** Laravel 13, PHP 8.3, Octane/FrankenPHP, MySQL 8.4, PHPUnit (class-style + RefreshDatabase), Nuxt 4 SSR + PrimeVue.

**Spec:** `docs/superpowers/specs/2026-06-06-ledger-confiabilidade-transacoes-design.md`

**Comando de teste:** rodar no container `app` — `php artisan test --filter=<Classe>` para alvo; `make test` para a suíte completa.

---

## File Structure

**Criar:**
- `app/Enums/LedgerEntryType.php` — tipos de movimento (backed enum string).
- `app/Enums/LedgerDirection.php` — credit/debit.
- `config/ledger.php` — chave HMAC.
- `database/migrations/2026_06_06_100001_create_ledger_entries_table.php`
- `database/migrations/2026_06_06_100002_add_ledger_chain_head_to_wallets_table.php`
- `app/Models/LedgerEntry.php`
- `database/factories/LedgerEntryFactory.php`
- `app/Services/LedgerService.php` — record + verificação (coração).
- `app/Http/Resources/Marketplace/LedgerEntryResource.php`
- `app/Http/Controllers/Wallet/WalletLedgerController.php` — extrato paginado.
- `app/Http/Controllers/Marketplace/LedgerVerificationController.php` — verify por hash.
- `app/Console/Commands/VerifyLedgerCommand.php` — `ledger:verify`.
- `tests/Feature/Ledger/LedgerVerificationTest.php`
- `tests/Feature/Ledger/WalletLedgerTest.php`
- `tests/Unit/LedgerServiceTest.php`
- `tests/Feature/Ledger/VerifyLedgerCommandTest.php`
- `frontend/app/components/ledger/LedgerReceipt.vue`
- `frontend/app/components/ledger/WalletLedgerTable.vue`

**Modificar:**
- `app/Models/Wallet.php` — fillable/casts das colunas de cabeça + relação `ledgerEntries`.
- `app/Services/WalletService.php` — `deposit` passa a usar transação+lock e apenda ledger.
- `app/Services/OrderService.php` — `purchase` + `release` apendam ledger.
- `app/Services/PixChargeService.php` — `confirmPaid` apenda ledger.
- `app/Services/BoostService.php` — `purchaseWithWallet` apenda ledger.
- `routes/api.php` — rotas `GET /api/wallet/ledger` e `GET /api/ledger/{hash}/verify`.
- `phpunit.xml` — env `LEDGER_HMAC_KEY` para testes.
- `.env.example` — `LEDGER_HMAC_KEY=`.
- `public/robots.txt` — Disallow das rotas novas (se ainda não coberto por prefixo).
- `frontend/app/pages/...` (carteira + detalhe da order) — montar os componentes novos.

---

## Task 1: Enums + config da chave HMAC

**Files:**
- Create: `app/Enums/LedgerEntryType.php`
- Create: `app/Enums/LedgerDirection.php`
- Create: `config/ledger.php`
- Modify: `.env.example`
- Modify: `phpunit.xml`

- [ ] **Step 1: Criar `app/Enums/LedgerEntryType.php`**

```php
<?php

namespace App\Enums;

enum LedgerEntryType: string
{
    case EscrowDebit = 'escrow_debit';
    case EscrowReleaseCredit = 'escrow_release_credit';
    case DepositCredit = 'deposit_credit';
    case PixTopupCredit = 'pix_topup_credit';
    case BoostDebit = 'boost_debit';
}
```

- [ ] **Step 2: Criar `app/Enums/LedgerDirection.php`**

```php
<?php

namespace App\Enums;

enum LedgerDirection: string
{
    case Credit = 'credit';
    case Debit = 'debit';
}
```

- [ ] **Step 3: Criar `config/ledger.php`**

```php
<?php

return [
    /*
     | Segredo do HMAC do ledger de confiabilidade. String aleatória longa.
     | Gerar: php -r "echo bin2hex(random_bytes(32));"
     | NUNCA versionar o valor real — só em .env (gitignored).
     | Rotacionar invalida a verificação das linhas antigas (limitação conhecida).
     */
    'hmac_key' => env('LEDGER_HMAC_KEY'),
];
```

- [ ] **Step 4: Adicionar a chave ao `.env.example`** (no fim do arquivo, antes da última linha em branco)

```dotenv
# Segredo do HMAC do ledger de confiabilidade (gerar: php -r "echo bin2hex(random_bytes(32));")
LEDGER_HMAC_KEY=
```

- [ ] **Step 5: Adicionar env de teste ao `phpunit.xml`** (dentro do bloco `<php>`, junto dos outros `<env>`)

```xml
<env name="LEDGER_HMAC_KEY" value="test-ledger-hmac-key-do-not-use-in-prod-0123456789abcdef"/>
```

- [ ] **Step 6: Commit**

```bash
git add app/Enums/LedgerEntryType.php app/Enums/LedgerDirection.php config/ledger.php .env.example phpunit.xml
git commit -m "feat(ledger): enums LedgerEntryType/Direction + config da chave HMAC"
```

---

## Task 2: Migrations + model LedgerEntry + colunas de cabeça na Wallet

**Files:**
- Create: `database/migrations/2026_06_06_100001_create_ledger_entries_table.php`
- Create: `database/migrations/2026_06_06_100002_add_ledger_chain_head_to_wallets_table.php`
- Create: `app/Models/LedgerEntry.php`
- Create: `database/factories/LedgerEntryFactory.php`
- Modify: `app/Models/Wallet.php`

- [ ] **Step 1: Criar a migration `create_ledger_entries_table`**

`database/migrations/2026_06_06_100001_create_ledger_entries_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wallet_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            $table->string('type', 32);        // enum App\Enums\LedgerEntryType
            $table->string('direction', 6);    // enum App\Enums\LedgerDirection (credit|debit)
            $table->bigInteger('amount_cents');          // sempre positivo; sinal vem do direction
            $table->bigInteger('balance_after_cents');   // snapshot do saldo após o movimento

            $table->string('reference_type', 32)->nullable(); // order|pix_charge|boost|deposit
            $table->bigInteger('reference_id')->nullable();    // id da linha de origem

            $table->unsignedBigInteger('seq');         // sequência por carteira (1,2,3…)
            $table->char('prev_hash', 64)->nullable(); // hash da linha anterior desta wallet (null = genesis)
            $table->char('hash', 64);                  // HMAC-SHA256 hex — código de confiabilidade

            $table->timestamps();

            $table->unique(['wallet_id', 'seq']); // detecta gap na cadeia da carteira
            $table->unique('hash');               // lookup por código no endpoint de verificação
            $table->index(['reference_type', 'reference_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ledger_entries');
    }
};
```

- [ ] **Step 2: Criar a migration `add_ledger_chain_head_to_wallets_table`**

`database/migrations/2026_06_06_100002_add_ledger_chain_head_to_wallets_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wallets', function (Blueprint $table) {
            // Âncora anti-truncamento: cabeça da cadeia de ledger desta carteira.
            $table->char('ledger_head_hash', 64)->nullable()->after('balance_cents');
            $table->unsignedBigInteger('ledger_seq')->default(0)->after('ledger_head_hash');
        });
    }

    public function down(): void
    {
        Schema::table('wallets', function (Blueprint $table) {
            $table->dropColumn(['ledger_head_hash', 'ledger_seq']);
        });
    }
};
```

- [ ] **Step 3: Criar o model `app/Models/LedgerEntry.php`**

```php
<?php

namespace App\Models;

use App\Enums\LedgerDirection;
use App\Enums\LedgerEntryType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'wallet_id',
    'user_id',
    'type',
    'direction',
    'amount_cents',
    'balance_after_cents',
    'reference_type',
    'reference_id',
    'seq',
    'prev_hash',
    'hash',
])]
class LedgerEntry extends Model
{
    /** @use HasFactory<\Database\Factories\LedgerEntryFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'type' => LedgerEntryType::class,
            'direction' => LedgerDirection::class,
            'amount_cents' => 'integer',
            'balance_after_cents' => 'integer',
            'reference_id' => 'integer',
            'seq' => 'integer',
        ];
    }

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
```

- [ ] **Step 4: Atualizar `app/Models/Wallet.php`** — fillable, casts e relação

Substituir o conteúdo da classe por:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['user_id', 'balance_cents', 'ledger_head_hash', 'ledger_seq'])]
class Wallet extends Model
{
    /** @use HasFactory<\Database\Factories\WalletFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'balance_cents' => 'integer',
            'ledger_seq' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(LedgerEntry::class);
    }
}
```

- [ ] **Step 5: Criar `database/factories/LedgerEntryFactory.php`**

(Usado só para montar cenários de auditoria/IDOR — fluxos reais geram entries com HMAC válido.)

```php
<?php

namespace Database\Factories;

use App\Enums\LedgerDirection;
use App\Enums\LedgerEntryType;
use App\Models\LedgerEntry;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LedgerEntry>
 */
class LedgerEntryFactory extends Factory
{
    protected $model = LedgerEntry::class;

    public function definition(): array
    {
        return [
            'wallet_id' => Wallet::factory(),
            'user_id' => User::factory(),
            'type' => LedgerEntryType::DepositCredit,
            'direction' => LedgerDirection::Credit,
            'amount_cents' => 1000,
            'balance_after_cents' => 1000,
            'reference_type' => 'deposit',
            'reference_id' => null,
            'seq' => 1,
            'prev_hash' => null,
            'hash' => str_repeat('0', 64),
        ];
    }
}
```

- [ ] **Step 6: Rodar as migrations e confirmar que aplicam**

Run: `php artisan migrate`
Expected: as duas migrations rodam sem erro (`...create_ledger_entries_table ... DONE`, `...add_ledger_chain_head_to_wallets_table ... DONE`).

- [ ] **Step 7: Commit**

```bash
git add database/migrations/2026_06_06_100001_create_ledger_entries_table.php \
        database/migrations/2026_06_06_100002_add_ledger_chain_head_to_wallets_table.php \
        app/Models/LedgerEntry.php app/Models/Wallet.php database/factories/LedgerEntryFactory.php
git commit -m "feat(ledger): tabela ledger_entries + âncora de cabeça na wallet + model/factory"
```

---

## Task 3: LedgerService — record + verificação (TDD)

**Files:**
- Create: `tests/Unit/LedgerServiceTest.php`
- Create: `app/Services/LedgerService.php`

- [ ] **Step 1: Escrever o teste que falha** — `tests/Unit/LedgerServiceTest.php`

```php
<?php

namespace Tests\Unit;

use App\Enums\LedgerDirection;
use App\Enums\LedgerEntryType;
use App\Models\User;
use App\Models\Wallet;
use App\Services\LedgerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LedgerServiceTest extends TestCase
{
    use RefreshDatabase;

    private function wallet(): Wallet
    {
        $user = User::factory()->create();

        return Wallet::factory()->for($user)->create();
    }

    public function test_record_creates_genesis_entry_and_updates_wallet_head(): void
    {
        $wallet = $this->wallet();
        $service = app(LedgerService::class);

        $entry = $service->record(
            $wallet,
            LedgerEntryType::DepositCredit,
            LedgerDirection::Credit,
            5_000,
            5_000,
            'deposit',
            null,
        );

        $this->assertSame(1, $entry->seq);
        $this->assertNull($entry->prev_hash);
        $this->assertSame(64, strlen($entry->hash));

        $wallet->refresh();
        $this->assertSame($entry->hash, $wallet->ledger_head_hash);
        $this->assertSame(1, $wallet->ledger_seq);
    }

    public function test_record_chains_second_entry_to_first(): void
    {
        $wallet = $this->wallet();
        $service = app(LedgerService::class);

        $first = $service->record($wallet, LedgerEntryType::DepositCredit, LedgerDirection::Credit, 5_000, 5_000, 'deposit', null);
        $second = $service->record($wallet, LedgerEntryType::BoostDebit, LedgerDirection::Debit, 2_000, 3_000, 'boost', 7);

        $this->assertSame(2, $second->seq);
        $this->assertSame($first->hash, $second->prev_hash);
        $this->assertNotSame($first->hash, $second->hash);

        $wallet->refresh();
        $this->assertSame($second->hash, $wallet->ledger_head_hash);
        $this->assertSame(2, $wallet->ledger_seq);
    }

    public function test_signature_valid_true_for_untampered_and_false_after_tamper(): void
    {
        $wallet = $this->wallet();
        $service = app(LedgerService::class);

        $entry = $service->record($wallet, LedgerEntryType::DepositCredit, LedgerDirection::Credit, 5_000, 5_000, 'deposit', null);
        $this->assertTrue($service->signatureValid($entry->fresh()));

        // Adultera o valor direto no banco (sem recomputar o HMAC).
        $entry->amount_cents = 9_999;
        $entry->saveQuietly();

        $this->assertFalse($service->signatureValid($entry->fresh()));
    }

    public function test_missing_hmac_key_fails_closed(): void
    {
        config(['ledger.hmac_key' => '']);
        $wallet = $this->wallet();

        $this->expectException(\RuntimeException::class);
        app(LedgerService::class)->record($wallet, LedgerEntryType::DepositCredit, LedgerDirection::Credit, 1, 1, 'deposit', null);
    }
}
```

- [ ] **Step 2: Rodar o teste e confirmar que falha**

Run: `php artisan test --filter=LedgerServiceTest`
Expected: FAIL — `Class "App\Services\LedgerService" not found`.

- [ ] **Step 3: Implementar `app/Services/LedgerService.php`**

```php
<?php

namespace App\Services;

use App\Enums\LedgerDirection;
use App\Enums\LedgerEntryType;
use App\Models\LedgerEntry;
use App\Models\Wallet;
use RuntimeException;

/**
 * Registro append-only de movimentos de saldo com HMAC encadeado por carteira.
 *
 * Stateless / Octane-safe: lê a chave de config a cada chamada, sem estado em
 * propriedade. `record` DEVE ser chamado dentro da transação do caller, com a
 * wallet já travada (lockForUpdate) — a cadeia depende disso para ser determinística.
 */
class LedgerService
{
    /**
     * Apenda uma linha na cadeia da carteira e atualiza a cabeça (head).
     *
     * Pré-condição: $lockedWallet já travada e dentro de uma transação aberta.
     */
    public function record(
        Wallet $lockedWallet,
        LedgerEntryType $type,
        LedgerDirection $direction,
        int $amountCents,
        int $balanceAfterCents,
        ?string $referenceType,
        ?int $referenceId,
    ): LedgerEntry {
        $prevHash = $lockedWallet->ledger_head_hash;
        $seq = (int) $lockedWallet->ledger_seq + 1;

        $hash = $this->hash(
            $lockedWallet->id,
            $seq,
            $type->value,
            $direction->value,
            $amountCents,
            $balanceAfterCents,
            $referenceType,
            $referenceId,
            $prevHash,
        );

        $entry = LedgerEntry::create([
            'wallet_id' => $lockedWallet->id,
            'user_id' => $lockedWallet->user_id,
            'type' => $type,
            'direction' => $direction,
            'amount_cents' => $amountCents,
            'balance_after_cents' => $balanceAfterCents,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'seq' => $seq,
            'prev_hash' => $prevHash,
            'hash' => $hash,
        ]);

        $lockedWallet->ledger_head_hash = $hash;
        $lockedWallet->ledger_seq = $seq;
        $lockedWallet->save();

        return $entry;
    }

    /** A assinatura HMAC da linha confere com os campos armazenados? */
    public function signatureValid(LedgerEntry $entry): bool
    {
        $expected = $this->hash(
            $entry->wallet_id,
            (int) $entry->seq,
            $entry->type->value,
            $entry->direction->value,
            (int) $entry->amount_cents,
            (int) $entry->balance_after_cents,
            $entry->reference_type,
            $entry->reference_id !== null ? (int) $entry->reference_id : null,
            $entry->prev_hash,
        );

        return hash_equals($expected, (string) $entry->hash);
    }

    /**
     * Linha íntegra E ligada corretamente à antecessora da mesma carteira.
     * Genesis (seq=1) exige prev_hash null; demais exigem prev_hash == hash da seq-1.
     */
    public function verifyEntry(LedgerEntry $entry): bool
    {
        if (! $this->signatureValid($entry)) {
            return false;
        }

        if ((int) $entry->seq === 1) {
            return $entry->prev_hash === null;
        }

        $predecessor = LedgerEntry::where('wallet_id', $entry->wallet_id)
            ->where('seq', (int) $entry->seq - 1)
            ->first();

        return $predecessor !== null
            && $entry->prev_hash !== null
            && hash_equals((string) $predecessor->hash, (string) $entry->prev_hash);
    }

    /** Monta o payload canônico (ordem fixa, só campos determinísticos) e devolve o HMAC. */
    private function hash(
        int $walletId,
        int $seq,
        string $type,
        string $direction,
        int $amountCents,
        int $balanceAfterCents,
        ?string $referenceType,
        ?int $referenceId,
        ?string $prevHash,
    ): string {
        $canonical = json_encode([
            'wallet_id' => $walletId,
            'seq' => $seq,
            'type' => $type,
            'direction' => $direction,
            'amount_cents' => $amountCents,
            'balance_after_cents' => $balanceAfterCents,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'prev_hash' => $prevHash,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return hash_hmac('sha256', $canonical, $this->key());
    }

    /** Chave HMAC — fail-closed se ausente (nunca grava ledger sem assinatura válida). */
    private function key(): string
    {
        $key = (string) config('ledger.hmac_key');

        if ($key === '') {
            throw new RuntimeException('LEDGER_HMAC_KEY não configurada — ledger não pode ser assinado.');
        }

        return $key;
    }
}
```

- [ ] **Step 4: Rodar o teste e confirmar que passa**

Run: `php artisan test --filter=LedgerServiceTest`
Expected: PASS (4 testes verdes).

- [ ] **Step 5: Commit**

```bash
git add app/Services/LedgerService.php tests/Unit/LedgerServiceTest.php
git commit -m "feat(ledger): LedgerService record + verificação HMAC encadeada"
```

---

## Task 4: Gancho no WalletService::deposit (vira tx+lock)

**Files:**
- Modify: `app/Services/WalletService.php`
- Test: `tests/Feature/Ledger/WalletLedgerTest.php` (criado aqui; expandido na Task 8)

- [ ] **Step 1: Escrever o teste que falha** — `tests/Feature/Ledger/WalletLedgerTest.php`

```php
<?php

namespace Tests\Feature\Ledger;

use App\Enums\LedgerEntryType;
use App\Models\LedgerEntry;
use App\Models\User;
use App\Models\Wallet;
use App\Services\LedgerService;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WalletLedgerTest extends TestCase
{
    use RefreshDatabase;

    public function test_deposit_appends_signed_credit_entry(): void
    {
        $user = User::factory()->create();
        Wallet::factory()->for($user)->create();

        app(WalletService::class)->deposit($user, 7_500);

        $entry = LedgerEntry::where('user_id', $user->id)->sole();
        $this->assertSame(LedgerEntryType::DepositCredit, $entry->type);
        $this->assertSame(7_500, $entry->amount_cents);
        $this->assertSame(7_500, $entry->balance_after_cents);
        $this->assertSame('deposit', $entry->reference_type);
        $this->assertSame(1, $entry->seq);
        $this->assertTrue(app(LedgerService::class)->signatureValid($entry));
    }
}
```

- [ ] **Step 2: Rodar o teste e confirmar que falha**

Run: `php artisan test --filter=WalletLedgerTest`
Expected: FAIL — `LedgerEntry::sole()` lança `NoItemsFoundException` (deposit ainda não apenda).

- [ ] **Step 3: Reescrever `app/Services/WalletService.php`** — transação + lock + append

```php
<?php

namespace App\Services;

use App\Enums\LedgerDirection;
use App\Enums\LedgerEntryType;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Support\Facades\DB;

class WalletService
{
    public function __construct(private readonly LedgerService $ledger) {}

    /** Retorna a carteira do usuário, criando-a se ainda não existir. */
    public function walletFor(User $user): Wallet
    {
        return Wallet::firstOrCreate(['user_id' => $user->id]);
    }

    /**
     * Credita saldo na carteira (top-up de demonstração do MVP).
     *
     * Antes era um increment lock-free; agora a escrita do saldo e o append no
     * ledger encadeado precisam ser atômicos e serializados → transação +
     * lockForUpdate na wallet (a cadeia por-carteira lê a cabeça sob o mesmo lock).
     */
    public function deposit(User $user, int $amountCents): Wallet
    {
        $this->walletFor($user); // garante a carteira fora do lock (evita create sob lock)

        DB::beginTransaction();
        try {
            $wallet = Wallet::where('user_id', $user->id)->lockForUpdate()->firstOrFail();
            $wallet->increment('balance_cents', $amountCents);

            $this->ledger->record(
                $wallet,
                LedgerEntryType::DepositCredit,
                LedgerDirection::Credit,
                $amountCents,
                $wallet->balance_cents,
                'deposit',
                null,
            );

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        return $wallet->refresh();
    }
}
```

- [ ] **Step 4: Rodar o teste e confirmar que passa**

Run: `php artisan test --filter=WalletLedgerTest`
Expected: PASS.

- [ ] **Step 5: Rodar a regressão de wallet/pix pra garantir que nada quebrou**

Run: `php artisan test --filter=PixTopUpTest`
Expected: PASS (deposit/topup intactos).

- [ ] **Step 6: Commit**

```bash
git add app/Services/WalletService.php tests/Feature/Ledger/WalletLedgerTest.php
git commit -m "feat(ledger): deposit vira tx+lock e apenda crédito no ledger"
```

---

## Task 5: Gancho no OrderService (purchase + release)

**Files:**
- Modify: `app/Services/OrderService.php`
- Test: `tests/Feature/Ledger/OrderLedgerTest.php`

- [ ] **Step 1: Escrever o teste que falha** — `tests/Feature/Ledger/OrderLedgerTest.php`

```php
<?php

namespace Tests\Feature\Ledger;

use App\Enums\LedgerEntryType;
use App\Enums\ListingStatus;
use App\Models\LedgerEntry;
use App\Models\Listing;
use App\Models\User;
use App\Models\Wallet;
use App\Services\LedgerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderLedgerTest extends TestCase
{
    use RefreshDatabase;

    public function test_purchase_and_release_each_append_one_signed_entry(): void
    {
        $seller = User::factory()->create();
        Wallet::factory()->for($seller)->create();
        $buyer = User::factory()->create();
        Wallet::factory()->for($buyer)->withBalance(100_000)->create();

        $listing = Listing::factory()->for($seller, 'seller')->create([
            'price_cents' => 10_000,
            'status' => ListingStatus::Active,
        ]);

        $orderId = $this->actingAs($buyer, 'sanctum')
            ->postJson('/api/orders', ['listing_id' => $listing->id])
            ->assertCreated()
            ->json('data.id');

        // Débito do comprador no ato da compra.
        $debit = LedgerEntry::where('user_id', $buyer->id)->sole();
        $this->assertSame(LedgerEntryType::EscrowDebit, $debit->type);
        $this->assertSame(10_000, $debit->amount_cents);
        $this->assertSame(90_000, $debit->balance_after_cents);
        $this->assertSame('order', $debit->reference_type);
        $this->assertSame($orderId, $debit->reference_id);

        // Dupla confirmação → libera; crédito do vendedor (valor - taxa).
        $this->actingAs($seller, 'sanctum')->postJson("/api/orders/{$orderId}/confirm-delivery")->assertOk();
        $this->actingAs($buyer, 'sanctum')->postJson("/api/orders/{$orderId}/confirm-receipt")->assertOk();

        $credit = LedgerEntry::where('user_id', $seller->id)->sole();
        $this->assertSame(LedgerEntryType::EscrowReleaseCredit, $credit->type);
        $this->assertSame(9_500, $credit->amount_cents);
        $this->assertSame(9_500, $credit->balance_after_cents);

        $ledger = app(LedgerService::class);
        $this->assertTrue($ledger->signatureValid($debit));
        $this->assertTrue($ledger->signatureValid($credit));
    }

    public function test_double_confirm_does_not_double_credit_ledger(): void
    {
        $seller = User::factory()->create();
        Wallet::factory()->for($seller)->create();
        $buyer = User::factory()->create();
        Wallet::factory()->for($buyer)->withBalance(100_000)->create();
        $listing = Listing::factory()->for($seller, 'seller')->create(['price_cents' => 10_000, 'status' => ListingStatus::Active]);

        $orderId = $this->actingAs($buyer, 'sanctum')
            ->postJson('/api/orders', ['listing_id' => $listing->id])->json('data.id');

        $this->actingAs($seller, 'sanctum')->postJson("/api/orders/{$orderId}/confirm-delivery")->assertOk();
        $this->actingAs($buyer, 'sanctum')->postJson("/api/orders/{$orderId}/confirm-receipt")->assertOk();
        // Reconfirma — deve ser no-op, sem segunda linha de crédito.
        $this->actingAs($buyer, 'sanctum')->postJson("/api/orders/{$orderId}/confirm-receipt")->assertOk();

        $this->assertSame(1, LedgerEntry::where('user_id', $seller->id)->count());
    }
}
```

- [ ] **Step 2: Rodar o teste e confirmar que falha**

Run: `php artisan test --filter=OrderLedgerTest`
Expected: FAIL — `LedgerEntry::sole()` não encontra linha (purchase/release ainda não apendam).

- [ ] **Step 3: Editar `app/Services/OrderService.php`** — injetar LedgerService e apendar

Adicionar imports no topo (junto dos `use` existentes):

```php
use App\Enums\LedgerDirection;
use App\Enums\LedgerEntryType;
```

Trocar o construtor:

```php
    public function __construct(
        private readonly XpService $xp,
        private readonly LedgerService $ledger,
    ) {}
```

Em `purchase`, logo **após** `$lockedListing->update(['status' => ListingStatus::Sold]);` e antes de `DB::commit();`, inserir:

```php
            $this->ledger->record(
                $buyerWallet,
                LedgerEntryType::EscrowDebit,
                LedgerDirection::Debit,
                $price,
                $buyerWallet->balance_cents,
                'order',
                $order->id,
            );
```

Em `release`, logo **após** `$sellerWallet->increment('balance_cents', $order->seller_payout_cents);`, inserir:

```php
        $this->ledger->record(
            $sellerWallet,
            LedgerEntryType::EscrowReleaseCredit,
            LedgerDirection::Credit,
            $order->seller_payout_cents,
            $sellerWallet->balance_cents,
            'order',
            $order->id,
        );
```

- [ ] **Step 4: Rodar o teste e confirmar que passa**

Run: `php artisan test --filter=OrderLedgerTest`
Expected: PASS (2 testes verdes).

- [ ] **Step 5: Rodar a regressão de escrow**

Run: `php artisan test --filter=EscrowFlowTest`
Expected: PASS (escrow intacto).

- [ ] **Step 6: Commit**

```bash
git add app/Services/OrderService.php tests/Feature/Ledger/OrderLedgerTest.php
git commit -m "feat(ledger): purchase debita e release credita no ledger encadeado"
```

---

## Task 6: Gancho no PixChargeService::confirmPaid

**Files:**
- Modify: `app/Services/PixChargeService.php`
- Test: `tests/Feature/Ledger/PixLedgerTest.php`

- [ ] **Step 1: Escrever o teste que falha** — `tests/Feature/Ledger/PixLedgerTest.php`

```php
<?php

namespace Tests\Feature\Ledger;

use App\Enums\LedgerEntryType;
use App\Enums\PixChargeStatus;
use App\Models\LedgerEntry;
use App\Models\PixCharge;
use App\Models\User;
use App\Models\Wallet;
use App\Services\LedgerService;
use App\Services\PixChargeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PixLedgerTest extends TestCase
{
    use RefreshDatabase;

    public function test_confirm_paid_appends_signed_credit_entry(): void
    {
        $user = User::factory()->create();
        Wallet::factory()->for($user)->create();

        $charge = PixCharge::factory()->for($user)->create([
            'amount_cents' => 12_000,
            'status' => PixChargeStatus::Created,
        ]);

        app(PixChargeService::class)->confirmPaid($charge, 'E2E-TEST-123');

        $entry = LedgerEntry::where('user_id', $user->id)->sole();
        $this->assertSame(LedgerEntryType::PixTopupCredit, $entry->type);
        $this->assertSame(12_000, $entry->amount_cents);
        $this->assertSame(12_000, $entry->balance_after_cents);
        $this->assertSame('pix_charge', $entry->reference_type);
        $this->assertSame($charge->id, $entry->reference_id);
        $this->assertTrue(app(LedgerService::class)->signatureValid($entry));
    }

    public function test_confirm_paid_twice_does_not_double_append(): void
    {
        $user = User::factory()->create();
        Wallet::factory()->for($user)->create();
        $charge = PixCharge::factory()->for($user)->create(['amount_cents' => 12_000, 'status' => PixChargeStatus::Created]);

        $service = app(PixChargeService::class);
        $service->confirmPaid($charge, 'E2E-1');
        $service->confirmPaid($charge->fresh(), 'E2E-1'); // já paga → no-op

        $this->assertSame(1, LedgerEntry::where('user_id', $user->id)->count());
    }
}
```

- [ ] **Step 2: Rodar o teste e confirmar que falha**

Run: `php artisan test --filter=PixLedgerTest`
Expected: FAIL — `sole()` não encontra a linha.

- [ ] **Step 3: Editar `app/Services/PixChargeService.php`**

Adicionar imports no topo:

```php
use App\Enums\LedgerDirection;
use App\Enums\LedgerEntryType;
```

Adicionar `LedgerService` ao construtor:

```php
    public function __construct(
        private readonly PushinPayService $gateway,
        private readonly WalletService $wallets,
        private readonly LedgerService $ledger,
    ) {}
```

Em `confirmPaid`, logo **após** `$wallet->increment('balance_cents', $locked->amount_cents);`, inserir:

```php
            $this->ledger->record(
                $wallet,
                LedgerEntryType::PixTopupCredit,
                LedgerDirection::Credit,
                $locked->amount_cents,
                $wallet->balance_cents,
                'pix_charge',
                $locked->id,
            );
```

- [ ] **Step 4: Rodar o teste e confirmar que passa**

Run: `php artisan test --filter=PixLedgerTest`
Expected: PASS.

- [ ] **Step 5: Regressão PIX**

Run: `php artisan test --filter=PixTopUpTest`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add app/Services/PixChargeService.php tests/Feature/Ledger/PixLedgerTest.php
git commit -m "feat(ledger): confirmPaid (PIX) credita no ledger encadeado"
```

---

## Task 7: Gancho no BoostService::purchaseWithWallet

**Files:**
- Modify: `app/Services/BoostService.php`
- Test: `tests/Feature/Ledger/BoostLedgerTest.php`

- [ ] **Step 1: Escrever o teste que falha** — `tests/Feature/Ledger/BoostLedgerTest.php`

```php
<?php

namespace Tests\Feature\Ledger;

use App\Enums\BoostTier;
use App\Enums\LedgerEntryType;
use App\Models\LedgerEntry;
use App\Models\Listing;
use App\Models\User;
use App\Models\Wallet;
use App\Services\BoostService;
use App\Services\LedgerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BoostLedgerTest extends TestCase
{
    use RefreshDatabase;

    public function test_boost_purchase_appends_signed_debit_entry(): void
    {
        $user = User::factory()->create();
        Wallet::factory()->for($user)->withBalance(100_000)->create();
        $listing = Listing::factory()->for($user, 'seller')->create();

        $tier = BoostTier::Basic;
        $boost = app(BoostService::class)->purchaseWithWallet($user, $listing, $tier);

        $entry = LedgerEntry::where('user_id', $user->id)->sole();
        $this->assertSame(LedgerEntryType::BoostDebit, $entry->type);
        $this->assertSame($tier->priceCents(), $entry->amount_cents);
        $this->assertSame('boost', $entry->reference_type);
        $this->assertSame($boost->id, $entry->reference_id);
        $this->assertSame(100_000 - $tier->priceCents(), $entry->balance_after_cents);
        $this->assertTrue(app(LedgerService::class)->signatureValid($entry));
    }
}
```

> **Nota:** confirmar o case real do enum `BoostTier` (`app/Enums/BoostTier.php`) — se não houver `Basic`, usar o primeiro case definido. Ajustar `$tier` conforme o arquivo.

- [ ] **Step 2: Rodar o teste e confirmar que falha**

Run: `php artisan test --filter=BoostLedgerTest`
Expected: FAIL — `sole()` não encontra a linha.

- [ ] **Step 3: Editar `app/Services/BoostService.php`**

Adicionar imports no topo:

```php
use App\Enums\LedgerDirection;
use App\Enums\LedgerEntryType;
```

Adicionar `LedgerService` ao construtor:

```php
    public function __construct(
        private readonly XpService $xp,
        private readonly LedgerService $ledger,
    ) {}
```

Em `purchaseWithWallet`, logo **após** `$boost = $this->createActiveBoost($listing, $user, $tier, BoostPaymentMethod::Wallet);` e antes de `$this->xp->award(...)`, inserir:

```php
            $this->ledger->record(
                $wallet,
                LedgerEntryType::BoostDebit,
                LedgerDirection::Debit,
                $priceCents,
                $wallet->balance_cents,
                'boost',
                $boost->id,
            );
```

- [ ] **Step 4: Rodar o teste e confirmar que passa**

Run: `php artisan test --filter=BoostLedgerTest`
Expected: PASS.

- [ ] **Step 5: Regressão boost**

Run: `php artisan test --filter=BoostTest`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add app/Services/BoostService.php tests/Feature/Ledger/BoostLedgerTest.php
git commit -m "feat(ledger): boost debita no ledger encadeado"
```

---

## Task 8: Resource + extrato `GET /api/wallet/ledger`

**Files:**
- Create: `app/Http/Resources/Marketplace/LedgerEntryResource.php`
- Create: `app/Http/Controllers/Wallet/WalletLedgerController.php`
- Modify: `routes/api.php`
- Test: `tests/Feature/Ledger/WalletLedgerTest.php` (expandir)

- [ ] **Step 1: Adicionar o teste de extrato** ao `tests/Feature/Ledger/WalletLedgerTest.php` (novo método na classe existente)

```php
    public function test_wallet_ledger_lists_only_own_entries_paginated(): void
    {
        $me = User::factory()->create();
        Wallet::factory()->for($me)->create();
        $other = User::factory()->create();
        Wallet::factory()->for($other)->create();

        app(WalletService::class)->deposit($me, 5_000);
        app(WalletService::class)->deposit($me, 3_000);
        app(WalletService::class)->deposit($other, 9_000);

        $this->actingAs($me, 'sanctum')
            ->getJson('/api/wallet/ledger')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.type', 'deposit_credit')
            ->assertJsonPath('data.0.code', fn ($code) => is_string($code) && strlen($code) === 64);
    }

    public function test_wallet_ledger_requires_auth(): void
    {
        $this->getJson('/api/wallet/ledger')->assertUnauthorized();
    }
```

- [ ] **Step 2: Rodar e confirmar que falha**

Run: `php artisan test --filter=WalletLedgerTest`
Expected: FAIL — rota `/api/wallet/ledger` não existe (404).

- [ ] **Step 3: Criar `app/Http/Resources/Marketplace/LedgerEntryResource.php`**

```php
<?php

namespace App\Http\Resources\Marketplace;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LedgerEntryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type->value,
            'direction' => $this->direction->value,
            'amount_cents' => $this->amount_cents,
            'balance_after_cents' => $this->balance_after_cents,
            'reference_type' => $this->reference_type,
            'reference_id' => $this->reference_id,
            'seq' => $this->seq,
            'code' => $this->hash, // código de confiabilidade (HMAC — seguro de exibir)
            'created_at' => $this->created_at,
        ];
    }
}
```

- [ ] **Step 4: Criar `app/Http/Controllers/Wallet/WalletLedgerController.php`**

```php
<?php

namespace App\Http\Controllers\Wallet;

use App\Http\Controllers\Controller;
use App\Http\Resources\Marketplace\LedgerEntryResource;
use App\Models\LedgerEntry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WalletLedgerController extends Controller
{
    /** Extrato do ledger do próprio usuário (escopado por user_id). */
    public function index(Request $request): JsonResponse
    {
        $paginator = LedgerEntry::query()
            ->where('user_id', $request->user()->id)
            ->latest('id')
            ->paginate(20);

        $paginator->through(
            fn (LedgerEntry $entry) => LedgerEntryResource::make($entry)->resolve($request),
        );

        return response()->json($paginator);
    }
}
```

- [ ] **Step 5: Registrar a rota em `routes/api.php`**

Adicionar o import no topo:

```php
use App\Http\Controllers\Wallet\WalletLedgerController;
```

Dentro do grupo `auth:sanctum`, logo após a linha do `GET /wallet`:

```php
    Route::get('/wallet/ledger', [WalletLedgerController::class, 'index'])->middleware('throttle:60,1');
```

- [ ] **Step 6: Rodar e confirmar que passa**

Run: `php artisan test --filter=WalletLedgerTest`
Expected: PASS (3 testes verdes).

- [ ] **Step 7: Commit**

```bash
git add app/Http/Resources/Marketplace/LedgerEntryResource.php \
        app/Http/Controllers/Wallet/WalletLedgerController.php routes/api.php \
        tests/Feature/Ledger/WalletLedgerTest.php
git commit -m "feat(ledger): extrato GET /api/wallet/ledger via Resource"
```

---

## Task 9: Endpoint de verificação `GET /api/ledger/{hash}/verify`

**Files:**
- Create: `app/Http/Controllers/Marketplace/LedgerVerificationController.php`
- Modify: `routes/api.php`
- Test: `tests/Feature/Ledger/LedgerVerificationTest.php`

- [ ] **Step 1: Escrever o teste que falha** — `tests/Feature/Ledger/LedgerVerificationTest.php`

```php
<?php

namespace Tests\Feature\Ledger;

use App\Models\LedgerEntry;
use App\Models\User;
use App\Models\Wallet;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LedgerVerificationTest extends TestCase
{
    use RefreshDatabase;

    private function ownerWithEntry(): array
    {
        $user = User::factory()->create();
        Wallet::factory()->for($user)->create();
        app(WalletService::class)->deposit($user, 5_000);
        $entry = LedgerEntry::where('user_id', $user->id)->sole();

        return [$user, $entry];
    }

    public function test_owner_verifies_own_entry_as_valid(): void
    {
        [$user, $entry] = $this->ownerWithEntry();

        $this->actingAs($user, 'sanctum')
            ->getJson("/api/ledger/{$entry->hash}/verify")
            ->assertOk()
            ->assertJsonPath('data.valid', true)
            ->assertJsonPath('data.type', 'deposit_credit')
            ->assertJsonPath('data.amount_cents', 5_000);
    }

    public function test_tampered_entry_verifies_as_invalid(): void
    {
        [$user, $entry] = $this->ownerWithEntry();

        // Adultera o valor direto no banco; o hash continua o antigo.
        $entry->amount_cents = 999_999;
        $entry->saveQuietly();

        $this->actingAs($user, 'sanctum')
            ->getJson("/api/ledger/{$entry->hash}/verify")
            ->assertOk()
            ->assertJsonPath('data.valid', false);
    }

    public function test_stranger_cannot_verify_others_entry_returns_404(): void
    {
        [, $entry] = $this->ownerWithEntry();
        $stranger = User::factory()->create();

        $this->actingAs($stranger, 'sanctum')
            ->getJson("/api/ledger/{$entry->hash}/verify")
            ->assertNotFound();
    }

    public function test_order_counterparty_can_verify_order_entry(): void
    {
        // Crédito de release pertence à wallet do vendedor; o comprador (contraparte
        // da order) também pode verificar a linha daquela order.
        $seller = User::factory()->create();
        Wallet::factory()->for($seller)->create();
        $buyer = User::factory()->create();
        Wallet::factory()->for($buyer)->withBalance(100_000)->create();
        $listing = \App\Models\Listing::factory()->for($seller, 'seller')->create([
            'price_cents' => 10_000,
            'status' => \App\Enums\ListingStatus::Active,
        ]);

        $orderId = $this->actingAs($buyer, 'sanctum')
            ->postJson('/api/orders', ['listing_id' => $listing->id])->json('data.id');
        $this->actingAs($seller, 'sanctum')->postJson("/api/orders/{$orderId}/confirm-delivery");
        $this->actingAs($buyer, 'sanctum')->postJson("/api/orders/{$orderId}/confirm-receipt");

        $credit = LedgerEntry::where('user_id', $seller->id)->sole();

        // Comprador NÃO é dono da wallet do vendedor, mas é parte da order.
        $this->actingAs($buyer, 'sanctum')
            ->getJson("/api/ledger/{$credit->hash}/verify")
            ->assertOk()
            ->assertJsonPath('data.valid', true);
    }

    public function test_verify_requires_auth(): void
    {
        [, $entry] = $this->ownerWithEntry();
        $this->getJson("/api/ledger/{$entry->hash}/verify")->assertUnauthorized();
    }
}
```

- [ ] **Step 2: Rodar e confirmar que falha**

Run: `php artisan test --filter=LedgerVerificationTest`
Expected: FAIL — rota não existe (404 em todos).

- [ ] **Step 3: Criar `app/Http/Controllers/Marketplace/LedgerVerificationController.php`**

```php
<?php

namespace App\Http\Controllers\Marketplace;

use App\Http\Controllers\Controller;
use App\Models\LedgerEntry;
use App\Models\Order;
use App\Services\LedgerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LedgerVerificationController extends Controller
{
    public function __construct(private readonly LedgerService $ledger) {}

    /**
     * Verifica a confiabilidade de uma linha pelo código (hash). Escopo: dono da
     * carteira OU contraparte da order referenciada. Mismatch → 404 (anti-enumeração).
     */
    public function show(Request $request, string $hash): JsonResponse
    {
        $entry = LedgerEntry::where('hash', $hash)->first();

        if ($entry === null || ! $this->canView($request->user()->id, $entry)) {
            abort(404);
        }

        return response()->json([
            'data' => [
                'valid' => $this->ledger->verifyEntry($entry),
                'type' => $entry->type->value,
                'direction' => $entry->direction->value,
                'amount_cents' => $entry->amount_cents,
                'reference_type' => $entry->reference_type,
                'reference_id' => $entry->reference_id,
                'created_at' => $entry->created_at,
            ],
        ]);
    }

    /** Dono da carteira da linha, ou parte da order quando a linha referencia uma order. */
    private function canView(int $userId, LedgerEntry $entry): bool
    {
        if ($entry->user_id === $userId) {
            return true;
        }

        if ($entry->reference_type === 'order' && $entry->reference_id !== null) {
            return Order::whereKey($entry->reference_id)
                ->where(function ($query) use ($userId) {
                    $query->where('buyer_id', $userId)->orWhere('seller_id', $userId);
                })
                ->exists();
        }

        return false;
    }
}
```

- [ ] **Step 4: Registrar a rota em `routes/api.php`**

Adicionar o import no topo:

```php
use App\Http\Controllers\Marketplace\LedgerVerificationController;
```

Dentro do grupo `auth:sanctum` (perto das rotas de order), adicionar — o `whereAlphaNumeric` restringe o param ao formato de hash hex:

```php
    Route::get('/ledger/{hash}/verify', [LedgerVerificationController::class, 'show'])
        ->whereAlphaNumeric('hash')->middleware('throttle:60,1');
```

- [ ] **Step 5: Rodar e confirmar que passa**

Run: `php artisan test --filter=LedgerVerificationTest`
Expected: PASS (5 testes verdes).

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/Marketplace/LedgerVerificationController.php routes/api.php \
        tests/Feature/Ledger/LedgerVerificationTest.php
git commit -m "feat(ledger): endpoint GET /api/ledger/{hash}/verify (escopo + 404 anti-IDOR)"
```

---

## Task 10: Comando de auditoria `ledger:verify`

**Files:**
- Create: `app/Console/Commands/VerifyLedgerCommand.php`
- Test: `tests/Feature/Ledger/VerifyLedgerCommandTest.php`

- [ ] **Step 1: Escrever o teste que falha** — `tests/Feature/Ledger/VerifyLedgerCommandTest.php`

```php
<?php

namespace Tests\Feature\Ledger;

use App\Models\LedgerEntry;
use App\Models\User;
use App\Models\Wallet;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VerifyLedgerCommandTest extends TestCase
{
    use RefreshDatabase;

    private function userWithChain(int $entries): User
    {
        $user = User::factory()->create();
        Wallet::factory()->for($user)->create();
        $service = app(WalletService::class);
        for ($i = 0; $i < $entries; $i++) {
            $service->deposit($user, 1_000);
        }

        return $user;
    }

    public function test_clean_chain_passes(): void
    {
        $this->userWithChain(3);

        $this->artisan('ledger:verify')
            ->expectsOutputToContain('OK')
            ->assertExitCode(0);
    }

    public function test_tampered_entry_fails(): void
    {
        $user = $this->userWithChain(3);
        $entry = LedgerEntry::where('user_id', $user->id)->where('seq', 2)->sole();
        $entry->amount_cents = 999_999;
        $entry->saveQuietly();

        $this->artisan('ledger:verify')
            ->assertExitCode(1);
    }

    public function test_truncated_last_entry_is_detected_via_head_mismatch(): void
    {
        $user = $this->userWithChain(3);
        // Deleta a última linha sem ajustar a cabeça da wallet → head aponta p/ hash inexistente.
        LedgerEntry::where('user_id', $user->id)->where('seq', 3)->delete();

        $this->artisan('ledger:verify')
            ->assertExitCode(1);
    }
}
```

- [ ] **Step 2: Rodar e confirmar que falha**

Run: `php artisan test --filter=VerifyLedgerCommandTest`
Expected: FAIL — comando `ledger:verify` não existe (`Command "ledger:verify" is not defined`).

- [ ] **Step 3: Criar `app/Console/Commands/VerifyLedgerCommand.php`**

```php
<?php

namespace App\Console\Commands;

use App\Models\LedgerEntry;
use App\Models\Wallet;
use App\Services\LedgerService;
use Illuminate\Console\Command;

class VerifyLedgerCommand extends Command
{
    protected $signature = 'ledger:verify {--wallet= : Verificar só a carteira informada}';

    protected $description = 'Audita a integridade das cadeias de ledger (HMAC + elo + cabeça)';

    public function handle(LedgerService $ledger): int
    {
        $query = Wallet::query();
        if ($this->option('wallet') !== null) {
            $query->whereKey((int) $this->option('wallet'));
        }

        $broken = 0;

        foreach ($query->cursor() as $wallet) {
            $breakReason = $this->verifyWalletChain($wallet, $ledger);

            if ($breakReason !== null) {
                $broken++;
                $this->error("wallet {$wallet->id}: {$breakReason}");
            }
        }

        if ($broken > 0) {
            $this->error("FALHA: {$broken} carteira(s) com cadeia quebrada.");

            return self::FAILURE;
        }

        $this->info('OK: todas as cadeias de ledger estão íntegras.');

        return self::SUCCESS;
    }

    /** Retorna o motivo da primeira quebra, ou null se a cadeia está íntegra. */
    private function verifyWalletChain(Wallet $wallet, LedgerService $ledger): ?string
    {
        $entries = LedgerEntry::where('wallet_id', $wallet->id)->orderBy('seq')->get();

        $expectedSeq = 1;
        $prevHash = null;

        foreach ($entries as $entry) {
            if ((int) $entry->seq !== $expectedSeq) {
                return "gap de seq: esperado {$expectedSeq}, achou {$entry->seq}";
            }

            if (! $ledger->signatureValid($entry)) {
                return "HMAC inválido na seq {$entry->seq}";
            }

            if ($entry->prev_hash !== $prevHash) {
                return "elo quebrado na seq {$entry->seq}";
            }

            $prevHash = $entry->hash;
            $expectedSeq++;
        }

        // Cabeça: a última linha calculada precisa bater com a head persistida (detecta truncamento).
        $lastSeq = $expectedSeq - 1;
        if ((int) $wallet->ledger_seq !== $lastSeq) {
            return "cabeça desalinhada: wallet.ledger_seq={$wallet->ledger_seq}, cadeia termina em {$lastSeq}";
        }

        if (($wallet->ledger_head_hash ?? null) !== $prevHash) {
            return 'cabeça desalinhada: ledger_head_hash não bate com a última linha';
        }

        return null;
    }
}
```

- [ ] **Step 4: Rodar e confirmar que passa**

Run: `php artisan test --filter=VerifyLedgerCommandTest`
Expected: PASS (3 testes verdes).

- [ ] **Step 5: Commit**

```bash
git add app/Console/Commands/VerifyLedgerCommand.php tests/Feature/Ledger/VerifyLedgerCommandTest.php
git commit -m "feat(ledger): comando ledger:verify (HMAC + elo + cabeça)"
```

---

## Task 11: Frontend — recibo + extrato (componente-first, PrimeVue)

**Files:**
- Create: `frontend/app/components/ledger/LedgerReceipt.vue`
- Create: `frontend/app/components/ledger/WalletLedgerTable.vue`
- Modify: página da carteira (ex. `frontend/app/pages/wallet.vue` ou equivalente — confirmar o path real antes)

> **Antes de começar:** rodar `ls frontend/app/pages` e `ls frontend/app/components` para confirmar os paths reais e o composable de fetch usado (ex. `useApi`/`$fetch`). Seguir o padrão de chamada à API já existente em outra página (ex. a que consome `/api/wallet`).

- [ ] **Step 1: Criar `frontend/app/components/ledger/LedgerReceipt.vue`**

Componente que mostra o código de confiabilidade copiável + ação "Verificar".

```vue
<script setup lang="ts">
import { ref } from 'vue'

const props = defineProps<{ code: string }>()

const verifying = ref(false)
const result = ref<null | { valid: boolean }>(null)

async function verify() {
  verifying.value = true
  try {
    const res = await $fetch<{ data: { valid: boolean } }>(`/api/ledger/${props.code}/verify`)
    result.value = res.data
  } finally {
    verifying.value = false
  }
}

const short = (h: string) => `${h.slice(0, 8)}…${h.slice(-8)}`
</script>

<template>
  <div class="flex items-center gap-2">
    <Tag :value="short(code)" severity="secondary" />
    <Button label="Verificar" size="small" text :loading="verifying" @click="verify" />
    <Tag
      v-if="result"
      :value="result.valid ? 'Legítima' : 'Adulterada'"
      :severity="result.valid ? 'success' : 'danger'"
    />
  </div>
</template>
```

- [ ] **Step 2: Criar `frontend/app/components/ledger/WalletLedgerTable.vue`**

Extrato do ledger via `DataTable`, com o recibo por linha.

```vue
<script setup lang="ts">
import { ref, onMounted } from 'vue'
import LedgerReceipt from './LedgerReceipt.vue'

type Entry = {
  id: number
  type: string
  direction: 'credit' | 'debit'
  amount_cents: number
  balance_after_cents: number
  code: string
  created_at: string
}

const entries = ref<Entry[]>([])
const loading = ref(true)

const brl = (cents: number) => (cents / 100).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' })

onMounted(async () => {
  try {
    const res = await $fetch<{ data: Entry[] }>('/api/wallet/ledger')
    entries.value = res.data
  } finally {
    loading.value = false
  }
})
</script>

<template>
  <Card>
    <template #title>Extrato (ledger de confiabilidade)</template>
    <template #content>
      <DataTable :value="entries" :loading="loading" dataKey="id" paginator :rows="10">
        <Column field="type" header="Tipo" />
        <Column header="Valor">
          <template #body="{ data }">
            <span :class="data.direction === 'credit' ? 'text-green-600' : 'text-red-600'">
              {{ data.direction === 'credit' ? '+' : '−' }}{{ brl(data.amount_cents) }}
            </span>
          </template>
        </Column>
        <Column header="Saldo após">
          <template #body="{ data }">{{ brl(data.balance_after_cents) }}</template>
        </Column>
        <Column header="Código de confiabilidade">
          <template #body="{ data }"><LedgerReceipt :code="data.code" /></template>
        </Column>
      </DataTable>
    </template>
  </Card>
</template>
```

- [ ] **Step 3: Montar o `WalletLedgerTable` na página da carteira**

Na página que já mostra o saldo (`/api/wallet`), importar e montar o componente abaixo do saldo (a página só monta — regra 11):

```vue
<WalletLedgerTable />
```

- [ ] **Step 4: Build do frontend e checagem visual**

Run: `make front-build`
Expected: build sem erro. Subir o dev (`make front-dev`), logar, abrir a carteira, fazer um depósito demo e confirmar que a linha aparece no extrato e o botão "Verificar" devolve "Legítima".

- [ ] **Step 5: Commit**

```bash
git add frontend/app/components/ledger/ frontend/app/pages/
git commit -m "feat(ledger): recibo de confiabilidade + extrato na carteira (PrimeVue)"
```

---

## Task 12: robots.txt + vault + skills + finish-feature + merge

**Files:**
- Modify: `public/robots.txt`
- Create: `.vault/Decisoes/ADR — ledger de confiabilidade HMAC encadeado.md`
- Create: `.vault/Features/2026-06-06 ledger-confiabilidade-transacoes.md`
- Modify: skills `rmt-schema`, `rmt-architecture`, `rmt-security`, `rmt-tests`, `rmt-context`
- Modify: `.vault/MOC.md`

- [ ] **Step 1: Conferir/atualizar `public/robots.txt`**

Run: `cat public/robots.txt`
Se já houver `Disallow: /api/`, nada a fazer (as rotas novas estão sob `/api`). Caso contrário, adicionar:

```
Disallow: /api/wallet/ledger
Disallow: /api/ledger/
```

- [ ] **Step 2: Criar o ADR** `.vault/Decisoes/ADR — ledger de confiabilidade HMAC encadeado.md`

Usar `.vault/Templates/ADR.md`. Conteúdo mínimo: contexto (rastreabilidade/legitimidade exigida por transação), decisão (ledger append-only + HMAC-SHA256 encadeado **por carteira** + âncora de cabeça na wallet + verify endpoint + comando), alternativas descartadas (cadeia global única — serializa tudo sob Octane; só SHA-256 — forjável; só HMAC sem cadeia — não detecta deleção; âncora Merkle global — over-engineering), consequências (limitação de rotação de chave; sem ordenação global; taxa fora do ledger), e links `[[2026-06-06 ledger-confiabilidade-transacoes]]` + `[[ADR — escrow dupla-confirmacao e carteira simulada]]`.

- [ ] **Step 3: Criar a Feature note** `.vault/Features/2026-06-06 ledger-confiabilidade-transacoes.md`

Usar `.vault/Templates/Feature.md`. Preencher "Decisões técnicas" (HMAC encadeado por wallet, append dentro da tx com wallet travada, fail-closed da chave) e "Como evitar no futuro / armadilhas" (deposit precisou virar tx+lock; nunca apendar fora do lock da wallet; created_at fora do HMAC).

- [ ] **Step 4: Atualizar as skills** (obrigatório — CLAUDE.md):
  - `rmt-schema`: tabela `ledger_entries` (colunas/índices), enums `LedgerEntryType`/`LedgerDirection`, colunas novas em `wallets` (`ledger_head_hash`, `ledger_seq`), model `LedgerEntry`.
  - `rmt-architecture`: `LedgerService` (record/verifyEntry, stateless), pontos de gancho nas 5 operações de dinheiro, comando `ledger:verify`, `deposit` agora com tx+lock.
  - `rmt-security`: modelo de confiança do HMAC (chave só em `.env`, fail-closed, código=HMAC seguro de exibir), escopo do verify (dono ou contraparte da order → 404 anti-enumeração), append dentro do lock da wallet.
  - `rmt-tests`: padrão dos testes de ledger (assinatura válida, idempotência não duplica linha, 404 IDOR no verify, comando exit-code).
  - `rmt-context`: rotas novas `GET /api/wallet/ledger` e `GET /api/ledger/{hash}/verify`.

- [ ] **Step 5: Atualizar `.vault/MOC.md`** com links pro novo ADR e Feature.

- [ ] **Step 6: Suíte completa verde**

Run: `make test`
Expected: toda a suíte PASS (incluindo regressões de escrow/pix/boost/wallet).

- [ ] **Step 7: Rodar o finish-feature subagent** (regra 14, obrigatório antes do commit)

`Agent(subagent_type="rmt-feature-finisher", ...)` — corrigir todos os bloqueadores apontados na mesma sessão.

- [ ] **Step 8: Commit final (docs/skills/vault)**

```bash
git add public/robots.txt .vault/ .claude/skills/
git commit -m "docs(ledger): ADR + feature note + skills + robots.txt"
```

- [ ] **Step 9: Integrar a branch** — usar a skill `superpowers:finishing-a-development-branch` para decidir merge/PR e fechar a branch `feat/ledger-confiabilidade`.

---

## Notas de implementação para o executor

- **Ordem de lock inalterada**: o append do ledger sempre usa a wallet **já travada** pelo serviço caller — nunca abrir novo lock nem tocar outra wallet dentro do `record`. Não muda a ordem listing→wallet existente (evita deadlock).
- **`balance_after_cents`**: após `increment`/`decrement`, o model em memória já reflete o saldo novo (Eloquent atualiza o atributo) — passar `$wallet->balance_cents` direto.
- **`created_at` fica fora do HMAC** de propósito (é DB-auto, não reproduzível na re-verificação). Só campos determinísticos entram no payload canônico.
- **Idempotência**: o append fica sempre dentro do caminho que roda uma-vez-só (guard de status sob lock em `release`/`confirmPaid`), então reconfirmar nunca duplica linha — coberto por teste em cada gancho.
- **Octane**: `LedgerService` é stateless (lê `config` por chamada); pode ser resolvido pelo container sem singleton de estado.
```
