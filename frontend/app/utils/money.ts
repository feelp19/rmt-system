/** Centavos inteiros → string em Real (R$). */
export const formatCents = (cents: number | null | undefined): string =>
  ((cents ?? 0) / 100).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' })

/** Reais (float da UI) → centavos inteiros para enviar ao backend. */
export const reaisToCents = (reais: number | null | undefined): number =>
  Math.round((reais ?? 0) * 100)
