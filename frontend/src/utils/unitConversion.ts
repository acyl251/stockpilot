// Base unit = grams for mass, millilitres for volume
const MASS_BASE: Record<string, number> = { kg: 1000, g: 1, mg: 0.001 }
const VOL_BASE:  Record<string, number> = { l: 1000, dl: 100, cl: 10, ml: 1 }

const DISCRETE_GROUPS: string[][] = [
  ['pièce', 'piece', 'pcs', 'unité', 'unite', 'u'],
  ['portion'],
  ['dose'],
  ['boite', 'boîte', 'bte'],
  ['paquet', 'pqt', 'pack'],
]

/**
 * Returns f such that: cout = prix_par_unite_stock × quantite_recette × f
 * Returns null when units are incompatible.
 */
export function getConversionFactor(uniteStock: string, uniteRecette: string): number | null {
  const us = uniteStock.toLowerCase().trim()
  const ur = uniteRecette.toLowerCase().trim()

  if (!us || !ur || us === ur) return 1

  if (MASS_BASE[us] !== undefined && MASS_BASE[ur] !== undefined)
    return MASS_BASE[ur] / MASS_BASE[us]

  if (VOL_BASE[us] !== undefined && VOL_BASE[ur] !== undefined)
    return VOL_BASE[ur] / VOL_BASE[us]

  for (const group of DISCRETE_GROUPS)
    if (group.includes(us) && group.includes(ur)) return 1

  return null
}
