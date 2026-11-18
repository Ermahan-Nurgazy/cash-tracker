/** Сохраняет Blob как файл через временную ссылку. */
export function saveBlob(blob: Blob, filename: string): void {
  const url = URL.createObjectURL(blob)
  const link = document.createElement('a')
  link.href = url
  link.download = filename
  document.body.appendChild(link)
  link.click()
  link.remove()
  // Браузеру нужно время начать скачивание
  setTimeout(() => URL.revokeObjectURL(url), 1000)
}
