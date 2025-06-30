import { isEmpty, isEmptyArray, isNullOrUndefined } from './helpers'

// 👉 Required Validator
export const requiredValidator = (value: unknown) => {
  if (isNullOrUndefined(value) || isEmptyArray(value) || value === false)
    return 'This field is required'

  return !!String(value).trim().length || 'This field is required'
}

// 👉 Email Validator
export const emailValidator = (value: unknown) => {
  if (isEmpty(value))
    return true

  const re = /^(?:[^<>()[\]\\.,;:\s@"]+(?:\.[^<>()[\]\\.,;:\s@"]+)*|".+")@(?:\[\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}\]|(?:[a-z\-\d]+\.)+[a-z]{2,})$/i

  if (Array.isArray(value))
    return value.every(val => re.test(String(val))) || 'The Email field must be a valid email'

  return re.test(String(value)) || 'The Email field must be a valid email'
}

// 👉 Password Validator
export const passwordValidator = (password: string) => {
  const regExp = /(?=.*\d)(?=.*[a-z])(?=.*[A-Z])(?=.*[!@#$%&*()]).{8,}/

  const validPassword = regExp.test(password)

  return validPassword || 'Field must contain at least one uppercase, lowercase, special character and digit with min 8 chars'
}

// 👉 Confirm Password Validator
export const confirmedValidator = (value: string, target: string) =>

  value === target || 'The Confirm Password field confirmation does not match'

// 👉 Between Validator
export const betweenValidator = (value: unknown, min: number, max: number) => {
  const valueAsNumber = Number(value)

  return (Number(min) <= valueAsNumber && Number(max) >= valueAsNumber) || `Enter number between ${min} and ${max}`
}

// 👉 Integer Validator
export const integerValidator = (value: unknown) => {
  if (isEmpty(value))
    return true

  if (Array.isArray(value))
    return value.every(val => /^-?\d+$/.test(String(val))) || 'This field must be an integer'

  return /^-?\d+$/.test(String(value)) || 'This field must be an integer'
}

// 👉 Regex Validator
export const regexValidator = (value: unknown, regex: RegExp | string): string | boolean => {
  if (isEmpty(value))
    return true

  let regeX = regex
  if (typeof regeX === 'string')
    regeX = new RegExp(regeX)

  if (Array.isArray(value))
    return value.every(val => regexValidator(val, regeX))

  return regeX.test(String(value)) || 'The Regex field format is invalid'
}

// 👉 Alpha Validator
export const alphaValidator = (value: unknown) => {
  if (isEmpty(value))
    return true

  return /^[A-Z]*$/i.test(String(value)) || 'The Alpha field may only contain alphabetic characters'
}

// 👉 URL Validator
export const urlValidator = (value: unknown) => {
  if (isEmpty(value))
    return true

  const re = /^https?:\/\/[^\s$.?#].\S*$/

  return re.test(String(value)) || 'URL is invalid'
}

// 👉 Length Validator
export const lengthValidator = (value: unknown, length: number) => {
  if (isEmpty(value))
    return true

  return String(value).length === length || `"The length of the Characters field must be ${length} characters."`
}

// 👉 Alpha-dash Validator
export const alphaDashValidator = (value: unknown) => {
  if (isEmpty(value))
    return true

  const valueAsString = String(value)

  return /^[\w-]*$/.test(valueAsString) || 'All Character are not valid'
}

// 👉 Number Validator (dla wartości niekoniecznie całkowitych)
export const numberValidator = (value: unknown): true | string => {
  if (isEmpty(value))
    return true
  const numValue = Number(value)

  return !isNaN(numValue) && isFinite(numValue) || 'Wartość musi być liczbą.'
}

// 👉 Min Value Validator (dla liczb)
export const minValueValidator = (value: unknown, min: number): true | string => {
  if (isEmpty(value))
    return true
  const numValue = Number(value)
  if (isNaN(numValue) || !isFinite(numValue))
    return 'Wartość musi być liczbą.' // Najpierw sprawdź, czy to liczba

  return numValue >= min || `Wartość musi być większa lub równa ${min}.`
}

// 👉 Max Value Validator (dla liczb)
export const maxValueValidator = (value: unknown, max: number): true | string => {
  if (isEmpty(value))
    return true
  const numValue = Number(value)
  if (isNaN(numValue) || !isFinite(numValue))
    return 'Wartość musi być liczbą.'

  return numValue <= max || `Wartość musi być mniejsza lub równa ${max}.`
}

// 👉 SKU Validator (przykładowy, dostosuj regex do swoich potrzeb)
export const skuValidator = (value: unknown): true | string => {
  if (isEmpty(value))
    return true // Jeśli nie jest wymagane, puste jest OK
  // Prosty przykład: litery, cyfry, myślnik, podkreślenie, min 3 znaki
  const re = /^[\w.-]{3,}$/

  return re.test(String(value)) || 'SKU zawiera nieprawidłowe znaki lub jest za krótkie.'
}

// 👉 EAN Validator (przykładowy, uproszczony - tylko cyfry, odpowiednia długość)
export const eanValidator = (value: unknown): true | string => {
  if (isEmpty(value))
    return true
  const sValue = String(value)

  // Regex dla 8, 12, 13, lub 14 cyfr (EAN-8, UPC-A, EAN-13, ITF-14/GTIN-14)
  const re = /^(\d{8}|\d{12}|\d{13}|\d{14})$/

  return re.test(sValue) || 'Nieprawidłowy format EAN (8, 12, 13 lub 14 cyfr).'
}
