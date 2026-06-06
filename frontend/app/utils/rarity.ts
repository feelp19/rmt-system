import type { Listing } from '~/types'

export type Rarity = 'gold' | 'rare' | 'epic' | 'legend'

/**
 * Metáfora loot: boost manda na raridade; sem boost, o tipo do anúncio decide.
 * basic→rare, intermediate→epic, advanced→legend; gold→ouro, item→rare.
 */
export function rarityFor(listing: Listing): Rarity {
  if (listing.boost) {
    if (listing.boost.tier === 'advanced') return 'legend'
    if (listing.boost.tier === 'intermediate') return 'epic'
    return 'rare'
  }
  return listing.type === 'gold' ? 'gold' : 'rare'
}

export const RARITY_VAR: Record<Rarity, string> = {
  gold: 'var(--gold)',
  rare: 'var(--rare)',
  epic: 'var(--epic)',
  legend: 'var(--legend)',
}
