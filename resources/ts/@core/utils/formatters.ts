import { isToday } from './helpers'

export const avatarText = (value: string) => {
  if (!value)
    return ''
  const nameArray = value.split(' ')

  return nameArray.map(word => word.charAt(0).toUpperCase()).join('')
}

export const kFormatter = (num: number) => {
  const numFormatter = new Intl.NumberFormat('en', {
    notation: 'compact',
    maximumFractionDigits: 1,
  })

  return numFormatter.format(num)
}

/**
 * Format and return date in Humanize format
 * @param {string} value date to format
 * @param {Intl.DateTimeFormatOptions} formatting Intl object to format with
 */
export const formatDate = (value: string, formatting: Intl.DateTimeFormatOptions = { month: 'short', day: 'numeric', year: 'numeric' }) => {
  if (!value)
    return value

  return new Intl.DateTimeFormat('pl-PL', formatting).format(new Date(value))
}

/**
 * Return short human friendly month representation of date
 * @param {string} value date to format
 * @param {boolean} toTimeForCurrentDay Shall convert to time if day is today/current
 */
export const formatDateToMonthShort = (value: string, toTimeForCurrentDay = true) => {
  const date = new Date(value)
  let formatting: Record<string, string> = { month: 'short', day: 'numeric' }

  if (toTimeForCurrentDay && isToday(date))
    formatting = { hour: 'numeric', minute: 'numeric' }

  return new Intl.DateTimeFormat('pl-PL', formatting).format(new Date(value))
}

export const prefixWithPlus = (value: number) => value > 0 ? `+${value}` : value

/**
 * Formats a number as a currency string.
 * @param {number | string} value The number to format.
 * @param {string} currency The currency code (e.g., 'PLN', 'USD').
 * @returns {string} The formatted currency string.
 */
export const formatCurrency = (value: number | string, currency = 'PLN'): string => {
  const numericValue = typeof value === 'string' ? Number.parseFloat(value) : value
  if (Number.isNaN(numericValue))
    return ''

  return new Intl.NumberFormat('pl-PL', {
    style: 'currency',
    currency,
  }).format(numericValue)
}
