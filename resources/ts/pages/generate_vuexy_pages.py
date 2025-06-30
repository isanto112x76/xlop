import os

# Zmień jeśli Twój katalog jest inny:
PAGES_ROOT = r"D:\projects\warehouse-system\resources\ts\pages"

# Lista wszystkich ścieżek do plików .vue według Twojego menu
VUE_PATHS = [
    "dashboard/index.vue",
    "dashboard/stock-summary.vue",
    "dashboard/recent-docs.vue",
    "dashboard/notifications.vue",
    "dashboard/quick-actions.vue",
    "dashboard/stats.vue",

    "products/index.vue",
    "products/view.vue",
    "products/edit.vue",
    "products/create.vue",
    "products/import-export.vue",
    "products/media.vue",
    "products/history.vue",
    "products/bulk-actions.vue",

    "categories/index.vue",
    "categories/create.vue",
    "categories/tree.vue",

    "documents/index.vue",
    "documents/view.vue",
    "documents/create.vue",
    "documents/pz.vue",
    "documents/wz.vue",
    "documents/rw.vue",
    "documents/mm.vue",
    "documents/inventory.vue",
    "documents/returns.vue",
    "documents/corrections.vue",
    "documents/edit.vue",
    "documents/pdf.vue",
    "documents/search.vue",
    "documents/history.vue",

    "stock/index.vue",
    "stock/actions.vue",
    "stock/reservations.vue",
    "stock/missing.vue",
    "stock/alerts.vue",
    "stock/export.vue",

    "orders/index.vue",
    "orders/view.vue",
    "orders/edit.vue",
    "orders/create.vue",
    "orders/status.vue",
    "orders/import-export.vue",
    "orders/history.vue",

    "suppliers/index.vue",
    "suppliers/view.vue",
    "suppliers/create.vue",
    "suppliers/history.vue",

    "contractors/index.vue",
    "contractors/view.vue",
    "contractors/create.vue",
    "contractors/history.vue",

    "reports/index.vue",
    "reports/stock.vue",
    "reports/sales.vue",
    "reports/documents.vue",
    "reports/history.vue",
    "reports/partners.vue",
    "reports/products.vue",

    "integrations/index.vue",
    "integrations/allegro/account.vue",
    "integrations/allegro/sync-products.vue",
    "integrations/allegro/import-export.vue",
    "integrations/allegro/orders.vue",
    "integrations/baselinker/account.vue",
    "integrations/baselinker/status-sync.vue",
    "integrations/baselinker/stock-sync.vue",
    "integrations/baselinker/mapping.vue",
    "integrations/other/list.vue",
    "integrations/other/config.vue",

    "users/index.vue",
    "users/roles.vue",
    "users/create.vue",
    "users/logs.vue",
    "users/reset-password.vue",

    "settings/index.vue",
    "settings/general.vue",
    "settings/warehouses.vue",
    "settings/documents.vue",
    "settings/notifications.vue",
    "settings/backup.vue",
    "settings/import.vue",

    "logs/index.vue",
    "logs/system.vue",
    "logs/documents.vue",
    "logs/stock.vue",
    "logs/users.vue",

    "help/index.vue",
    "help/faq.vue",
    "help/video.vue",
    "help/contact.vue",
]

PLACEHOLDER = """<template>
  <div>Strona w budowie – {{ path }}</div>
</template>
"""

for rel_path in VUE_PATHS:
    file_path = os.path.join(PAGES_ROOT, rel_path)
    folder = os.path.dirname(file_path)
    if not os.path.exists(folder):
        os.makedirs(folder)
    if not os.path.exists(file_path):
        with open(file_path, 'w', encoding='utf-8') as f:
            f.write(PLACEHOLDER.replace('{{ path }}', rel_path))
        print(f"Utworzono: {file_path}")
    else:
        print(f"Już istnieje: {file_path}")

print("Gotowe! Wszystkie pliki i foldery utworzone.")
