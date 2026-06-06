import type { AuthUser } from '~/types'

interface RegisterPayload {
  name: string
  email: string
  password: string
  password_confirmation: string
}

interface LoginPayload {
  email: string
  password: string
}

interface SessionResponse {
  token: string
  user: AuthUser
}

/** Estado de autenticação: token em cookie + usuário em estado compartilhado. */
export const useAuth = () => {
  const token = useCookie<string | null>('rmt_token', {
    maxAge: 60 * 60 * 24 * 30,
    sameSite: 'lax',
  })
  const user = useState<AuthUser | null>('auth_user', () => null)
  const api = useApi()

  const setSession = (res: SessionResponse) => {
    token.value = res.token
    user.value = res.user
  }

  const register = (payload: RegisterPayload) =>
    api.post<SessionResponse>('/register', payload).then(setSession)

  const login = (payload: LoginPayload) =>
    api.post<SessionResponse>('/login', payload).then(setSession)

  const fetchMe = async () => {
    if (!token.value) {
      user.value = null
      return
    }
    try {
      const res = await api.get<{ user: AuthUser }>('/me')
      user.value = res.user
    } catch {
      // Token inválido/expirado: limpa a sessão.
      token.value = null
      user.value = null
    }
  }

  const logout = async () => {
    try {
      await api.post('/logout')
    } catch {
      // Token já pode estar inválido — segue limpando localmente.
    }
    token.value = null
    user.value = null
  }

  return {
    token,
    user,
    register,
    login,
    logout,
    fetchMe,
    isAuthenticated: computed(() => user.value !== null),
  }
}
