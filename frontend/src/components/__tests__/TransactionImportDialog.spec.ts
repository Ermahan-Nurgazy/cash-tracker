import { flushPromises, mount } from '@vue/test-utils'
import PrimeVue from 'primevue/config'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { ApiError } from '@/api/errors'
import * as api from '@/api/transactions'
import TransactionImportDialog from '../TransactionImportDialog.vue'

vi.mock('@/api/transactions')

async function mountDialog() {
  const wrapper = mount(TransactionImportDialog, {
    props: { visible: true },
    global: { plugins: [PrimeVue] },
    attachTo: document.body,
  })
  await flushPromises()
  await new Promise((resolve) => setTimeout(resolve, 30))
  return wrapper
}

function pick(file: File) {
  const input = document.body.querySelector('[data-test="file"]') as HTMLInputElement
  Object.defineProperty(input, 'files', { value: [file], configurable: true })
  input.dispatchEvent(new Event('change'))
}

const click = async (selector: string) => {
  ;(document.body.querySelector(selector) as HTMLElement).click()
  await flushPromises()
}

const text = (selector: string) => document.body.querySelector(selector)?.textContent ?? ''
const csv = () => new File(['Дата;Тип'], 'ops.csv', { type: 'text/csv' })

describe('TransactionImportDialog', () => {
  beforeEach(() => {
    vi.resetAllMocks()
    document.body.innerHTML = ''
  })

  it('без выбранного файла ничего не отправляет', async () => {
    await mountDialog()

    await click('[data-test="upload"]')

    expect(api.importTransactions).not.toHaveBeenCalled()
    expect(text('[data-test="form-error"]')).toContain('Выберите CSV-файл.')
  })

  it('отправляет выбранный файл, сообщает число операций и закрывается', async () => {
    vi.mocked(api.importTransactions).mockResolvedValue({ imported: 5 })
    const wrapper = await mountDialog()
    const file = csv()

    pick(file)
    await flushPromises()
    expect(text('[data-test="file-name"]')).toBe('ops.csv')
    await click('[data-test="upload"]')

    expect(api.importTransactions).toHaveBeenCalledWith(file)
    expect(wrapper.emitted('imported')?.[0]).toEqual([5])
    expect(wrapper.emitted('update:visible')?.[0]).toEqual([false])
  })

  it('показывает ошибки строк и остаётся открытым', async () => {
    vi.mocked(api.importTransactions).mockRejectedValue(
      new ApiError(422, 'Импорт отменён: в файле есть ошибки, ничего не сохранено.', {
        'Строка 3': 'Счёт «X» не найден.',
        'Строка 5': 'Тип «перевод»: нужно «расход» или «доход».',
      }),
    )
    const wrapper = await mountDialog()

    pick(csv())
    await click('[data-test="upload"]')

    const items = [...document.body.querySelectorAll('[data-test="row-errors"] li')].map(
      (li) => li.textContent ?? '',
    )
    expect(items).toHaveLength(2)
    expect(items[0]).toContain('Строка 3')
    expect(text('[data-test="form-error"]')).toContain('Импорт отменён')
    expect(wrapper.emitted('imported')).toBeUndefined()
  })

  it('отклоняет файл больше 1 МБ без запроса', async () => {
    await mountDialog()

    pick(new File([new Uint8Array(1024 * 1024 + 1)], 'big.csv'))
    await flushPromises()

    expect(text('[data-test="form-error"]')).toContain('слишком большой')
    expect(text('[data-test="file-name"]')).toBe('Файл не выбран')
  })
})
