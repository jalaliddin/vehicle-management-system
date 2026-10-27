// Labels and units for fuel codes returned by the API.

export const transactionKinds = { receipt: 'Qabul', refuel: 'Quyish', adjustment: 'Inventarizatsiya' }
export const fuelTypes = { benzin: 'Benzin', dizel: 'Dizel', gaz: 'Gaz' }

const units = { benzin: 'L', dizel: 'L', gaz: 'm³' }
export const unitOf = (fuelType) => units[fuelType] ?? 'L'

// "1250.50" → "1 250,50"
export const quantity = (value) => (value === null || value === undefined || value === '')
  ? '—'
  : Number(value).toLocaleString('uz-UZ', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
