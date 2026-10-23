export function unitCodePrefix(name: string) {
  const letters = name
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .toUpperCase()
    .replace(/[^A-Z]/g, '')

  return `${letters}XX`.slice(0, 2)
}

export function generateUnitCode(name: string, randomNumber?: number) {
  const number =
    randomNumber ?? globalThis.crypto.getRandomValues(new Uint32Array(1))[0]

  return `${unitCodePrefix(name)}${String(number % 10000).padStart(4, '0')}`
}
