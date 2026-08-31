export type User = {
  id: string
  email: string
  displayName: string
  roles: string[]
  status: 'ACTIVE' | 'SUSPENDED'
}

export type AuthPayload = {
  token: string
  expiresAt: string
  user: User
}
