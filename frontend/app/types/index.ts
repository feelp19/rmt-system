export type ListingType = 'item' | 'gold'
export type ListingStatus = 'active' | 'sold' | 'cancelled'
export type OrderStatus = 'awaiting_confirmation' | 'completed' | 'cancelled'
export type BoostTier = 'basic' | 'intermediate' | 'advanced'

export interface ListingBoost {
  tier: BoostTier
  tier_label: string
  expires_at: string
}

export interface PublicUser {
  id: number
  name: string
}

export interface AuthUser {
  id: number
  name: string
  email: string
}

export interface Wallet {
  id: number
  balance_cents: number
  updated_at: string
}

export type PixChargeStatus = 'created' | 'paid' | 'expired' | 'canceled'

export interface PixCharge {
  id: number
  amount_cents: number
  status: PixChargeStatus
  qr_code: string
  qr_code_base64: string
  expires_at: string
  paid_at: string | null
  created_at: string
}

export interface Listing {
  id: number
  game: string
  type: ListingType
  title: string
  description: string | null
  quantity: number
  price_cents: number
  status: ListingStatus
  seller: PublicUser | null
  boost?: ListingBoost | null
  created_at: string
}

export interface Order {
  id: number
  status: OrderStatus
  amount_cents: number
  fee_cents: number
  seller_payout_cents: number
  seller_confirmed_at: string | null
  buyer_confirmed_at: string | null
  completed_at: string | null
  listing: Listing | null
  buyer: PublicUser | null
  seller: PublicUser | null
  created_at: string
}

export interface Paginated<T> {
  data: T[]
  current_page: number
  last_page: number
  total: number
}
