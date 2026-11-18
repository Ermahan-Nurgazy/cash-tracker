// jsdom не реализует matchMedia, а компоненты PrimeVue (Select, DatePicker) его используют
if (typeof window.matchMedia !== 'function') {
  // Обычная функция, а не vi.fn(): vi.resetAllMocks() в тестах не должен её сбрасывать
  window.matchMedia = (query: string) =>
    ({
      matches: false,
      media: query,
      onchange: null,
      addEventListener: () => undefined,
      removeEventListener: () => undefined,
      addListener: () => undefined,
      removeListener: () => undefined,
      dispatchEvent: () => false,
    }) as MediaQueryList
}

// ResizeObserver тоже отсутствует в jsdom (его использует выпадающий список Select)
if (typeof globalThis.ResizeObserver === 'undefined') {
  globalThis.ResizeObserver = class {
    observe() {}
    unobserve() {}
    disconnect() {}
  } as unknown as typeof ResizeObserver
}
