import assert from 'node:assert/strict'
import { createServer } from 'vite'
import { createElement as h } from 'react'
import { renderToStaticMarkup } from 'react-dom/server'
import { QueryClientProvider } from '@tanstack/react-query'
import { MemoryRouter } from 'react-router-dom'

// Isolated render and pagination checks; no backend requests or database writes.
const server = await createServer({
  server: { middlewareMode: true, hmr: false, ws: false, watch: null },
  optimizeDeps: { noDiscovery: true, include: [] },
  appType: 'custom',
})
let checks = 0
try {
  const { queryClient } = await server.ssrLoadModule('/src/lib/queryClient.ts')
  const { keys } = await server.ssrLoadModule('/src/lib/queries.ts')
  const { api } = await server.ssrLoadModule('/src/lib/api.ts')
  const { DashboardPage } = await server.ssrLoadModule(
    '/src/features/dashboard/pages/DashboardPage.tsx',
  )
  const { getDashboardInventory } = await server.ssrLoadModule(
    '/src/features/dashboard/hooks/useAdminDashboard.ts',
  )
  const unit = { id: 7, name: 'Outlet Uji', code: 'OUT', type: 'OUTLET' }
  const salesKey = ['/transactions', 'admin-dashboard', 7, 7]
  const permissions = [
    'dashboard.view',
    'transaction.view',
    'transaction.create',
    'inventory.view',
    'transfer.view',
  ]
  const sales = {
    period: {
      from: '2026-11-14',
      to: '2026-11-20',
      days: 7,
      timezone: 'Asia/Jakarta',
    },
    revenue: '12500.50',
    transactions: 2,
    average_transaction: '6250.25',
    items_sold: 5,
    visitors: 8,
    daily: Array.from({ length: 7 }, (_, index) => ({
      date: `2026-11-${14 + index}`,
      revenue: index === 6 ? '12500.50' : '0.00',
      transactions: index === 6 ? 2 : 0,
    })),
    top_items: [
      {
        id: 1,
        name: '<script>unsafe</script>',
        quantity: 5,
        revenue: '12500.50',
        uom: 'botol',
      },
    ],
    recent_transactions: [],
    generated_at: '2026-11-20T12:00:00+07:00',
  }
  function session(role = 'admin', allowed = permissions, type = 'OUTLET') {
    queryClient.clear()
    queryClient.setQueryData(keys.auth, {
      id: 1,
      name: 'Petugas Uji',
      role: { slug: role },
      operational_unit: { ...unit, type },
      active_assignment: { id: 1 },
      permissions: allowed,
    })
  }
  function render() {
    return renderToStaticMarkup(
      h(
        QueryClientProvider,
        { client: queryClient },
        h(MemoryRouter, null, h(DashboardPage)),
      ),
    )
  }
  function check(name, run) {
    run()
    checks += 1
    console.log(`PASS ${name}`)
  }
  session()
  check(
    'Loading never displays zero revenue or a false healthy inventory',
    () => {
      const html = render()
      assert.match(html, /Memuat data/)
      assert.doesNotMatch(
        html,
        /Rp|Tidak ada stok kritis|Tidak ada transfer menunggu/,
      )
    },
  )
  queryClient.setQueryData(salesKey, sales)
  queryClient.setQueryData(
    ['/operational-units/7/inventory', 'dashboard'],
    [
      {
        item: { id: 1, name: 'Produk sehat' },
        available_quantity: 10,
        minimum_stock: 2,
        stock_urgency: 'HEALTHY',
      },
      {
        item: { id: 2, name: 'Produk habis' },
        available_quantity: 0,
        minimum_stock: 2,
        stock_urgency: 'OUT_OF_STOCK',
      },
      {
        item: { id: 3, name: 'Produk rendah' },
        available_quantity: 2,
        minimum_stock: 2,
        stock_urgency: 'BELOW_MINIMUM',
      },
    ],
  )
  check(
    'Sales, accessible chart, stock warnings and escaped product names render',
    () => {
      const html = render()
      assert.match(html, /12\.500,5/)
      assert.match(html, /6\.250,25/)
      assert.match(html, /Waktu pembayaran WIB/)
      assert.match(html, /2 produk/)
      assert.match(html, /Produk habis/)
      assert.match(html, /&lt;script&gt;unsafe&lt;\/script&gt;/)
      assert.doesNotMatch(html, /<script>unsafe|NaN|undefined/)
      assert.match(html, /aria-pressed="true">7 hari terakhir/)
      assert.match(html, /aria-pressed="false">30 hari terakhir/)
    },
  )
  session('admin', ['dashboard.view'])
  check(
    'Missing permissions hide sales inventory transfers and transaction actions',
    () => {
      const html = render()
      assert.match(html, /Akses Cepat/)
      assert.doesNotMatch(
        html,
        /Ringkasan penjualan|Kondisi persediaan|Transfer masuk|Buka POS|Rp/,
      )
    },
  )
  session('admin', permissions, 'TICKETING')
  queryClient.setQueryData(salesKey, sales)
  check('Ticketing uses visitors services and its own workspace link', () => {
    const html = render()
    assert.match(html, /Jumlah pengunjung|Layanan terlaris/)
    assert.match(html, /Buka Ticketing/)
    assert.doesNotMatch(html, /Buka POS|Item terjual/)
  })
  for (const role of ['warehouse', 'superadmin']) {
    session(role, ['dashboard.view'])
    check(`${role} retains the existing dashboard`, () => {
      const html = render()
      assert.match(html, /Akses Cepat/)
      assert.doesNotMatch(html, /RUANG KERJA ADMIN|Ringkasan penjualan/)
    })
  }
  session()
  queryClient.setQueryData(salesKey, {
    ...sales,
    revenue: '0.00',
    transactions: 0,
    average_transaction: '0.00',
    items_sold: 0,
    visitors: 0,
    daily: sales.daily.map((day) => ({
      ...day,
      revenue: '0.00',
      transactions: 0,
    })),
    top_items: [],
  })
  check('Empty sales explain how charts and rankings become populated', () => {
    const html = render()
    assert.match(html, /Penjualan pertama dimulai di sini/)
    assert.match(html, /Belum ada item terjual/)
    assert.doesNotMatch(html, /NaN|Infinity/)
  })
  queryClient.removeQueries({ queryKey: salesKey })
  queryClient.setQueryDefaults(salesKey, { retryOnMount: false })
  await queryClient
    .fetchQuery({
      queryKey: salesKey,
      queryFn: () => Promise.reject(new Error('Dashboard unavailable')),
      retry: false,
    })
    .catch(() => {})
  check(
    'A failed query shows retry instead of presenting sales as zero',
    () => {
      const html = render()
      assert.match(html, /Coba lagi/)
      assert.doesNotMatch(html, /Rp|Penjualan pertama dimulai di sini/)
    },
  )
  const adapter = api.defaults.adapter
  try {
    const pages = []
    api.defaults.adapter = async (config) => {
      pages.push(config.params.page ?? 1)
      return {
        data: {
          data: [{ item: { id: config.params.page ?? 1 } }],
          meta: { last_page: 2 },
        },
        status: 200,
        statusText: 'OK',
        headers: {},
        config,
      }
    }
    const inventory = await getDashboardInventory(
      '/operational-units/7/inventory',
    )
    check('Inventory totals include subsequent API pages', () => {
      assert.deepEqual(pages, [1, 2])
      assert.deepEqual(
        inventory.map((row) => row.item.id),
        [1, 2],
      )
    })
    api.defaults.adapter = async (config) => {
      if (config.params.page === 2) throw new Error('Second page failed')
      return {
        data: { data: [{ item: { id: 1 } }], meta: { last_page: 2 } },
        status: 200,
        statusText: 'OK',
        headers: {},
        config,
      }
    }
    await assert.rejects(
      getDashboardInventory('/operational-units/7/inventory'),
      /Second page failed/,
    )
    checks += 1
    console.log('PASS A failed inventory page rejects incomplete totals')
  } finally {
    api.defaults.adapter = adapter
  }
  queryClient.clear()
  console.log(`${checks} dashboard checks passed`)
} finally {
  await server.close()
}
