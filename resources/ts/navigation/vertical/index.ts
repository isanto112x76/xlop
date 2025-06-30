import type { VerticalNavItems } from '@/@layouts/types'

const verticalNavItems: VerticalNavItems = [
  // 1. Dashboard
  {
    heading: 'Dashboard',
    action: 'view',
    subject: 'dashboard',
  },
  {
    title: 'Pulpit',
    icon: { icon: 'tabler-layout-dashboard' },
    to: { path: '/dashboard' }, // Converted to path object
    action: 'view',
    subject: 'dashboard',
    children: [
      { title: 'Podsumowanie stanów', to: { path: '/dashboard/stock-summary' }, icon: { icon: 'tabler-box' } },
      { title: 'Ostatnie dokumenty', to: { path: '/dashboard/recent-docs' }, icon: { icon: 'tabler-file-text' } },
      { title: 'Powiadomienia', to: { path: '/dashboard/notifications' }, icon: { icon: 'tabler-bell' } },
      { title: 'Szybkie akcje', to: { path: '/dashboard/quick-actions' }, icon: { icon: 'tabler-flash' } },
      { title: 'Statystyki i wykresy', to: { path: '/dashboard/stats' }, icon: { icon: 'tabler-chart-bar' } },
    ],
  },

  // 2. Produkty
  {
    heading: 'Magazyn',
    action: 'manage',
    subject: 'all',
  },
  {
    title: 'Produkty',
    icon: { icon: 'tabler-package' },
    to: { path: '/products' }, // Converted to path object
    action: 'manage',
    subject: 'all',
    children: [
      { title: 'Lista produktów', to: { path: '/products' }, icon: { icon: 'tabler-list-details' } },
      {
        title: 'Szczegóły produktu',
        icon: { icon: 'mdi-magnify' },
        action: 'openProductSearchModal',
      },
      { title: 'Edycja produktu', to: { path: '/products/edit/:id' }, icon: { icon: 'tabler-pencil' } }, // Consider if :id is needed
      { title: 'Dodaj produkt', to: { path: '/products/add' }, icon: { icon: 'tabler-plus' } },
      { title: 'Import/Eksport', to: { path: '/products/import-export' }, icon: { icon: 'tabler-upload' } },
      { title: 'Zdjęcia/Media', to: { path: '/products/media' }, icon: { icon: 'tabler-photo' } },
      { title: 'Historia zmian', to: { path: '/products/history' }, icon: { icon: 'tabler-history' } },
      { title: 'Masowe akcje', to: { path: '/products/bulk-actions' }, icon: { icon: 'tabler-settings' } },
    ],
  },

  // 3. Kategorie produktów
  {
    title: 'Kategorie produktów',
    icon: { icon: 'tabler-category-2' },
    to: { path: '/categories' }, // Converted to path object
    action: 'manage',
    subject: 'all',
    children: [
      { title: 'Lista kategorii', to: { path: '/categories' }, icon: { icon: 'tabler-list' } },
      { title: 'Dodaj/edytuj kategorię', to: { path: '/categories/create' }, icon: { icon: 'tabler-plus' } }, // Consider if :id for edit
      { title: 'Drzewo kategorii', to: { path: '/categories/tree' }, icon: { icon: 'tabler-tree' } },
    ],
  },

  // 6. Zamówienia
  {
    title: 'Zamówienia',
    icon: { icon: 'tabler-shopping-cart' },
    to: { path: '/orders' }, // Converted to path object
    action: 'manage',
    subject: 'all',
    children: [
      { title: 'Lista zamówień', to: { path: '/orders' }, icon: { icon: 'tabler-list' } },
      { title: 'Podgląd zamówienia', to: { path: '/orders/view' }, icon: { icon: 'tabler-eye' } }, // Consider if :id is needed
      { title: 'Edycja zamówienia', to: { path: '/orders/edit' }, icon: { icon: 'tabler-pencil' } }, // Consider if :id is needed
      { title: 'Dodaj zamówienie', to: { path: '/orders/create' }, icon: { icon: 'tabler-plus' } },
      { title: 'Statusy zamówień', to: { path: '/orders/status' }, icon: { icon: 'tabler-flag' } },
      { title: 'Eksport/Import', to: { path: '/orders/import-export' }, icon: { icon: 'tabler-upload' } },
      { title: 'Historia i powiązane dokumenty', to: { path: '/orders/history' }, icon: { icon: 'tabler-history' } },
    ],
  },

  // 4. Dokumenty magazynowe
  {
    title: 'Dokumenty magazynowe',
    icon: { icon: 'tabler-file-text' },
    to: { path: '/documents' }, // Converted to path object
    action: 'manage',
    subject: 'all',
    children: [
      { title: 'Lista dokumentów', to: { path: '/documents/list' }, icon: { icon: 'tabler-list' } },
      { title: 'Podgląd dokumentu', to: { path: '/documents/view' }, icon: { icon: 'tabler-eye' } }, // Consider if :id is needed
      { title: 'Dodaj dokument', to: { path: '/documents/add/' }, icon: { icon: 'tabler-plus' } },
      {
        title: 'Typy dokumentów',
        icon: { icon: 'tabler-forms' },
        children: [
          { title: 'PZ – Przyjęcie zewnętrzne', to: { path: '/documents/pz' }, icon: { icon: 'tabler-file-plus' } },
          { title: 'WZ – Wydanie zewnętrzne', to: { path: '/documents/wz' }, icon: { icon: 'tabler-file-minus' } },
          { title: 'RW – Rozchód wewnętrzny', to: { path: '/documents/rw' }, icon: { icon: 'tabler-file-export' } },
          { title: 'MM – Przesunięcie MM', to: { path: '/documents/mm' }, icon: { icon: 'tabler-arrows-shuffle' } },
          { title: 'Inwentaryzacja', to: { path: '/documents/inventory' }, icon: { icon: 'tabler-clipboard-list' } },
          { title: 'Zwroty', to: { path: '/documents/returns' }, icon: { icon: 'tabler-refresh' } },
          { title: 'Korekty', to: { path: '/documents/corrections' }, icon: { icon: 'tabler-adjustments' } },
        ],
      },
      { title: 'Edycja dokumentu', to: { path: '/documents/edit' }, icon: { icon: 'tabler-pencil' } }, // Consider if :id is needed
      { title: 'Drukuj PDF', to: { path: '/documents/pdf' }, icon: { icon: 'tabler-printer' } }, // Consider if :id is needed
      { title: 'Wyszukiwarka', to: { path: '/documents/search' }, icon: { icon: 'tabler-search' } },
      { title: 'Historia zmian', to: { path: '/documents/history' }, icon: { icon: 'tabler-history' } },
    ],
  },

  // 5. Stany magazynowe
  {
    title: 'Stany magazynowe',
    icon: { icon: 'tabler-box-multiple' },
    to: { path: '/stock' }, // Converted to path object
    action: 'view',
    subject: 'all',
    children: [
      { title: 'Lista stanów', to: { path: '/stock' }, icon: { icon: 'tabler-list' } },
      { title: 'Akcje wymagane', to: { path: '/stock/actions' }, icon: { icon: 'tabler-alert-triangle' } },
      { title: 'Rezerwacje i oczekiwania', to: { path: '/stock/reservations' }, icon: { icon: 'tabler-clock' } },
      { title: 'Zestawienie braków', to: { path: '/stock/missing' }, icon: { icon: 'tabler-alert-octagon' } },
      { title: 'Alerty', to: { path: '/stock/alerts' }, icon: { icon: 'tabler-bell' } },
      { title: 'Eksport raportu', to: { path: '/stock/export' }, icon: { icon: 'tabler-download' } },
    ],
  },

  // 7. Dostawcy
  {
    title: 'Dostawcy',
    icon: { icon: 'tabler-truck-delivery' },
    to: { path: '/suppliers' }, // Converted to path object
    action: 'manage',
    subject: 'all',
    children: [
      { title: 'Lista dostawców', to: { path: '/suppliers' }, icon: { icon: 'tabler-list' } },
      { title: 'Szczegóły dostawcy', to: { path: '/suppliers/view' }, icon: { icon: 'tabler-eye' } }, // Consider if :id is needed
      { title: 'Dodaj/edytuj', to: { path: '/suppliers/create' }, icon: { icon: 'tabler-plus' } }, // Consider if :id for edit
      { title: 'Historia współpracy', to: { path: '/suppliers/history' }, icon: { icon: 'tabler-history' } },
    ],
  },

  // 8. Kontrahenci
  {
    title: 'Kontrahenci (Klienci)',
    icon: { icon: 'tabler-users' },
    to: { path: '/contractors' }, // Converted to path object
    action: 'manage',
    subject: 'all',
    children: [
      { title: 'Lista kontrahentów', to: { path: '/contractors' }, icon: { icon: 'tabler-list' } },
      { title: 'Szczegóły', to: { path: '/contractors/view' }, icon: { icon: 'tabler-eye' } }, // Consider if :id is needed
      { title: 'Dodaj/edytuj', to: { path: '/contractors/create' }, icon: { icon: 'tabler-plus' } }, // Consider if :id for edit
      { title: 'Historia współpracy', to: { path: '/contractors/history' }, icon: { icon: 'tabler-history' } },
    ],
  },

  // 9. Raporty i analizy
  {
    heading: 'Raporty',
    action: 'view',
    subject: 'all',
  },
  {
    title: 'Raporty i analizy',
    icon: { icon: 'tabler-chart-bar' },
    to: { path: '/reports' }, // Converted to path object
    action: 'view',
    subject: 'all',
    children: [
      { title: 'Stany magazynowe', to: { path: '/reports/stock' }, icon: { icon: 'tabler-box' } },
      { title: 'Obroty i sprzedaż', to: { path: '/reports/sales' }, icon: { icon: 'tabler-currency-dollar' } },
      { title: 'Dokumenty wg typu', to: { path: '/reports/documents' }, icon: { icon: 'tabler-file-text' } },
      { title: 'Historia zmian', to: { path: '/reports/history' }, icon: { icon: 'tabler-history' } },
      { title: 'Dostawcy i klienci', to: { path: '/reports/partners' }, icon: { icon: 'tabler-users' } },
      { title: 'Raporty produktów', to: { path: '/reports/products' }, icon: { icon: 'tabler-package' } },
    ],
  },

  // 10. Integracje
  {
    title: 'Integracje',
    icon: { icon: 'tabler-plug' },
    to: { path: '/integrations' }, // Converted to path object
    action: 'view',
    subject: 'all',
    children: [
      {
        title: 'Allegro',
        icon: { icon: 'tabler-brand-allegro' },
        children: [
          { title: 'Połączenie z kontem', to: { path: '/integrations/allegro/account' }, icon: { icon: 'tabler-link' } },
          { title: 'Synchronizacja produktów', to: { path: '/integrations/allegro/sync-products' }, icon: { icon: 'tabler-refresh' } },
          { title: 'Import/Eksport aukcji', to: { path: '/integrations/allegro/import-export' }, icon: { icon: 'tabler-upload' } },
          { title: 'Obsługa zamówień Allegro', to: { path: '/integrations/allegro/orders' }, icon: { icon: 'tabler-shopping-cart' } },
        ],
      },
      {
        title: 'BaseLinker',
        icon: { icon: 'tabler-brand-linkedin' }, // Note: tabler-brand-linkedin might not be BaseLinker specific
        children: [
          { title: 'Połączenie z kontem', to: { path: '/integrations/baselinker/account' }, icon: { icon: 'tabler-link' } },
          { title: 'Synchronizacja statusów', to: { path: '/integrations/baselinker/status-sync' }, icon: { icon: 'tabler-refresh' } },
          { title: 'Aktualizacja stanów magazynowych', to: { path: '/integrations/baselinker/stock-sync' }, icon: { icon: 'tabler-sync' } },
          { title: 'Mapowanie produktów', to: { path: '/integrations/baselinker/mapping' }, icon: { icon: 'tabler-map' } },
        ],
      },
      {
        title: 'Inne integracje',
        icon: { icon: 'tabler-puzzle' },
        children: [
          { title: 'Lista integracji', to: { path: '/integrations/other/list' }, icon: { icon: 'tabler-list' } },
          { title: 'Konfiguracja połączeń', to: { path: '/integrations/other/config' }, icon: { icon: 'tabler-settings' } },
        ],
      },
    ],
  },

  // 11. Użytkownicy i uprawnienia
  {
    heading: 'Administracja',
    action: 'manage',
    subject: 'all',
  },
  {
    title: 'Użytkownicy i uprawnienia',
    icon: { icon: 'tabler-users-group' },
    to: { path: '/users' }, // Converted to path object
    action: 'manage',
    subject: 'all',
    children: [
      { title: 'Lista użytkowników', to: { path: '/users' }, icon: { icon: 'tabler-list' } },
      { title: 'Role i uprawnienia', to: { path: '/users/roles' }, icon: { icon: 'tabler-lock' } },
      { title: 'Dodaj/edytuj użytkownika', to: { path: '/users/create' }, icon: { icon: 'tabler-plus' } }, // Consider if :id for edit
      { title: 'Historia logowań i akcji', to: { path: '/users/logs' }, icon: { icon: 'tabler-history' } },
      { title: 'Resetowanie haseł', to: { path: '/users/reset-password' }, icon: { icon: 'tabler-key' } },
    ],
  },

  // 12. Ustawienia
  {
    title: 'Ustawienia',
    icon: { icon: 'tabler-settings' },
    to: { path: '/settings' }, // Converted to path object
    action: 'manage',
    subject: 'all',
    children: [
      { title: 'Ogólne ustawienia', to: { path: '/settings/general' }, icon: { icon: 'tabler-settings' } },
      { title: 'Magazyny', to: { path: '/settings/warehouses' }, icon: { icon: 'tabler-building-warehouse' } },
    ],
  },

  // 13. Historia operacji
  {
    title: 'Historia operacji',
    icon: { icon: 'tabler-history' },
    to: { path: '/logs' }, // Converted to path object
    action: 'manage',
    subject: 'all',
    children: [
      { title: 'Logi systemowe', to: { path: '/logs/system' }, icon: { icon: 'tabler-file' } },
      { title: 'Dokumenty', to: { path: '/logs/documents' }, icon: { icon: 'tabler-file-text' } },
      { title: 'Stany magazynowe', to: { path: '/logs/stock' }, icon: { icon: 'tabler-box' } },
      { title: 'Działania użytkowników', to: { path: '/logs/users' }, icon: { icon: 'tabler-users' } },
    ],
  },

  // 14. Pomoc i dokumentacja
  {
    title: 'Pomoc',
    icon: { icon: 'tabler-help' },
    to: { path: '/help' }, // Converted to path object
    action: 'view',
    subject: 'all',
    children: [
      { title: 'FAQ', to: { path: '/help/faq' }, icon: { icon: 'tabler-question-mark' } },
      { title: 'Instrukcje wideo', to: { path: '/help/video' }, icon: { icon: 'tabler-video' } },
      { title: 'Kontakt', to: { path: '/help/contact' }, icon: { icon: 'tabler-mail' } },
    ],
  },
]

export default verticalNavItems
