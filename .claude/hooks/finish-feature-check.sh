#!/usr/bin/env bash
# PreToolUse hook: intercepta `git commit` e exige rodar rmt-feature-finisher
# antes de commits que fechem feature (>= 30 linhas alteradas).
#
# Bypass: SKIP_FINISH_CHECK=1 git commit ...
# Ignora: --amend, merge commits, commits triviais (< 30 linhas).

set -euo pipefail

# --- Lê input JSON do Claude Code ---
input="$(cat)"

# Extrai comando do tool_input.command (formato: {"tool_input":{"command":"..."}})
cmd="$(printf '%s' "$input" | python3 -c 'import json,sys; d=json.load(sys.stdin); print(d.get("tool_input",{}).get("command",""))' 2>/dev/null || echo "")"

# --- Filtros de allow ---

# Não é git commit → permite
if ! printf '%s' "$cmd" | grep -qE '(^|[^a-zA-Z])git[[:space:]]+commit([[:space:]]|$)'; then
  exit 0
fi

# Amend / no-edit / merge resolution → permite (não é fim de feature nova)
if printf '%s' "$cmd" | grep -qE -- '--amend|--no-edit|--continue|--squash'; then
  exit 0
fi

# Bypass explícito via env
if [ "${SKIP_FINISH_CHECK:-}" = "1" ]; then
  exit 0
fi

# --- Mede tamanho do diff staged ---
cd "${CLAUDE_PROJECT_DIR:-.}" 2>/dev/null || exit 0

stat_line="$(git diff --cached --shortstat 2>/dev/null || echo "")"
ins="$(printf '%s' "$stat_line" | grep -oE '[0-9]+ insertion' | grep -oE '[0-9]+' || echo 0)"
dels="$(printf '%s' "$stat_line" | grep -oE '[0-9]+ deletion' | grep -oE '[0-9]+' || echo 0)"
total=$((ins + dels))

# Commit trivial → permite
if [ "$total" -lt 30 ]; then
  exit 0
fi

# --- Bloqueia: instrui Claude a rodar subagent ---
cat <<EOF
{
  "decision": "block",
  "reason": "Commit grande detectado ($total linhas alteradas). Antes de prosseguir, INVOQUE o subagent: Agent(subagent_type=\"rmt-feature-finisher\", description=\"Pre-commit feature audit\", prompt=\"Audita branch atual contra checklist completo de arquitetura, segurança, frontend, vault e skills. Reporte bloqueadores em pt-br.\"). Se retornar bloqueador, corrija antes de commitar. Se OK ou já corrigiu, refaça o commit com prefixo: SKIP_FINISH_CHECK=1 git commit ..."
}
EOF
exit 0
