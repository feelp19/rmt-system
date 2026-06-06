/** Saldo da carteira em estado compartilhado + ações de leitura/depósito. */
export const useWallet = () => {
  const api = useApi()
  const { isAuthenticated } = useAuth()
  const balance = useState<number | null>('wallet_balance', () => null)

  const refresh = async () => {
    if (!isAuthenticated.value) {
      balance.value = null
      return
    }
    try {
      const res = await api.get<{ data: { balance_cents: number } }>('/wallet')
      balance.value = res.data.balance_cents
    } catch {
      balance.value = null
    }
  }

  const deposit = async (amountCents: number) => {
    const res = await api.post<{ data: { balance_cents: number } }>('/wallet/deposit', {
      amount_cents: amountCents,
    })
    balance.value = res.data.balance_cents
    return res.data
  }

  return { balance, refresh, deposit }
}
