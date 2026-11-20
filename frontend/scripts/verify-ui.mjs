import assert from 'node:assert/strict'
import { createServer } from 'vite'
import { createElement as h } from 'react'
import { renderToStaticMarkup } from 'react-dom/server'
import { QueryClientProvider, QueryObserver } from '@tanstack/react-query'
import { MemoryRouter, Route, Routes } from 'react-router-dom'
import { LazyMotion, domAnimation } from 'motion/react'

// In-memory fixtures only. This suite neither calls nor modifies the backend.
const server = await createServer({
  server: { middlewareMode: true, hmr: false, ws: false, watch: null },
  optimizeDeps: { noDiscovery: true, include: [] },
  appType: 'custom',
})
let checks = 0
try {
  const { queryClient } = await server.ssrLoadModule('/src/lib/queryClient.ts')
  const { keys } = await server.ssrLoadModule('/src/lib/queries.ts')
  const { apiError } = await server.ssrLoadModule('/src/lib/errors.ts')
  const { notifications, TOAST_DURATION } = await server.ssrLoadModule(
    '/src/lib/notifications.ts',
  )
  const { ToastNotifications } = await server.ssrLoadModule(
    '/src/components/common/ToastNotifications.tsx',
  )
  const { PermissionGuard } = await server.ssrLoadModule(
    '/src/features/auth/RouteGuard.tsx',
  )
  const { landingRouteFor } = await server.ssrLoadModule(
    '/src/features/auth/landingRoute.ts',
  )
  const { Sidebar } = await server.ssrLoadModule(
    '/src/components/common/Sidebar.tsx',
  )
  const { Topbar } = await server.ssrLoadModule(
    '/src/components/common/Topbar.tsx',
  )
  const { ErrorState, ListContent } = await server.ssrLoadModule(
    '/src/components/ui/index.tsx',
  )
  const { formatCurrency, formatRupiahInput, parseRupiahInput } =
    await server.ssrLoadModule('/src/utils/format.ts')
  const { presentActivity } = await server.ssrLoadModule(
    '/src/features/account/activityPresentation.ts',
  )
  const unit = {
    id: 7,
    code: 'TEST',
    name: 'Unit Fixture',
    type: 'TICKETING',
    scanner_enabled: false,
  }
  const user = {
    id: 1,
    name: 'Fixture',
    username: 'fixture-user',
    email: 'fixture@example.test',
    is_active: true,
    role: { id: 1, name: 'Admin', slug: 'admin' },
    operational_unit: unit,
    permissions: [],
  }
  function session(
    role,
    permissions,
    operationalUnit = role === 'admin' ? unit : null,
  ) {
    queryClient.clear()
    const activeAssignment = {
      id: 1,
      is_active: true,
      role: { ...user.role, slug: role },
      operational_unit: operationalUnit,
    }
    queryClient.setQueryData(keys.auth, {
      ...user,
      role: activeAssignment.role,
      operational_unit: operationalUnit,
      assignments: [activeAssignment],
      active_assignment: activeAssignment,
      permissions,
    })
  }
  function render(element, initialEntries = ['/']) {
    return renderToStaticMarkup(
      h(
        QueryClientProvider,
        { client: queryClient },
        h(
          LazyMotion,
          { features: domAnimation },
          h(MemoryRouter, { initialEntries }, element),
        ),
      ),
    )
  }
  function check(name, assertion) {
    assertion()
    checks += 1
    console.log(`PASS ${name}`)
  }
  check('Rupiah input formats thousands and preserves decimal values', () => {
    assert.equal(formatRupiahInput('7000'), '7.000')
    assert.equal(formatRupiahInput('12500.50'), '12.500,50')
    assert.equal(parseRupiahInput('7.000'), '7000')
    assert.equal(parseRupiahInput('Rp 12.500,50'), '12500.50')
    assert.equal(parseRupiahInput('1.234.567'), '1234567')
  })
  const toastId = notifications.success('Perubahan berhasil disimpan.')
  check('Global toast renders a five-second dismissible status message', () => {
    const html = render(h(ToastNotifications))
    assert.match(html, /Perubahan berhasil disimpan/)
    assert.match(html, new RegExp(`data-duration="${TOAST_DURATION}"`))
    assert.match(html, /Tutup notifikasi/)
  })
  notifications.dismiss(toastId)
  function guard(permission, transaction = false) {
    return render(
      h(
        Routes,
        null,
        h(
          Route,
          { element: h(PermissionGuard, { permission, transaction }) },
          h(Route, { path: '/', element: h('p', null, 'AUTHORIZED_CONTENT') }),
        ),
      ),
    )
  }
  const { ForgotPasswordPage } = await server.ssrLoadModule(
    '/src/features/auth/ForgotPasswordPage.tsx',
  )
  check('Forgot password page exposes the username recovery step', () => {
    const html = render(h(ForgotPasswordPage))
    assert.match(html, /Lupa password/)
    assert.match(html, /Kirim kode OTP/)
    assert.match(html, /name="username"/)
  })
  session('admin', ['dashboard.view'])
  check('Permission guard denies missing permission', () =>
    assert.match(guard('user.view'), /Akses tidak diizinkan/),
  )
  check('Permission guard renders authorized route', () =>
    assert.match(guard('dashboard.view'), /AUTHORIZED_CONTENT/),
  )
  check('Sidebar hides inaccessible master and POS links', () => {
    const html = render(h(Sidebar))
    assert.match(html, /Dashboard/)
    assert.doesNotMatch(html, /href="\/users"|href="\/pos"/)
  })
  check('Topbar owns the page title and description', () => {
    const html = render(
      h(Topbar, { onMenu() {} }),
      ['/activity-logs'],
    )
    assert.match(html, /Log Aktivitas/)
    assert.match(html, /Tinjau jejak perubahan dan aktivitas pengguna/)
    assert.doesNotMatch(html, /page-header/)
  })
  check('Topbar accepts dynamic workspace context', () => {
    const html = render(
      h(Topbar, {
        onMenu() {},
        override: {
          label: 'POS',
          description: 'Outlet 1 · OU0001',
        },
      }),
      ['/pos'],
    )
    assert.match(html, /Outlet 1 · OU0001/)
  })
  session('admin', ['transaction.create'])
  check('Ticketing admin sees Ticketing without the outlet POS', () => {
    const html = render(h(Sidebar))
    assert.match(html, /href="\/ticketing"[^>]*>.*Ticketing/)
    assert.doesNotMatch(html, /href="\/pos"/)
  })
  session('admin', ['transaction.create'], { ...unit, type: 'OUTLET' })
  check('Outlet admin sees POS without Ticketing', () => {
    const html = render(h(Sidebar))
    assert.match(html, /href="\/pos"[^>]*>.*POS/)
    assert.doesNotMatch(html, /href="\/ticketing"/)
  })
  const { PosPage } = await server.ssrLoadModule(
    '/src/features/transactions/PosPage.tsx',
  )
  const { CART_SYNC_INTERVAL_MS, getCartSyncInterval, usePosCart } =
    await server.ssrLoadModule('/src/features/transactions/usePosCart.ts')
  check('Scanner-enabled carts synchronize while they remain open', () => {
    assert.equal(getCartSyncInterval(true, 'CART', false), CART_SYNC_INTERVAL_MS)
    assert.equal(getCartSyncInterval(false, 'CART', false), false)
    assert.equal(getCartSyncInterval(true, 'PAID', false), false)
    assert.equal(getCartSyncInterval(true, 'CART', true), false)
  })
  session('admin', ['transaction.create'], {
    ...unit,
    type: 'OUTLET',
    scanner_enabled: true,
  })
  check('POS does not expose manual barcode input', () => {
    const html = render(h(PosPage))
    assert.match(html, /Katalog Item/)
    assert.doesNotMatch(html, /Input Barcode|Masukkan barcode/)
  })
  session('superadmin', ['transaction.create'])
  check('Superadmin can open both transaction workspaces', () => {
    const html = render(h(Sidebar))
    assert.match(html, /href="\/pos"/)
    assert.match(html, /href="\/ticketing"/)
  })
  const { ForecastsPage } = await server.ssrLoadModule(
    '/src/features/forecasts/ForecastsPage.tsx',
  )
  check('AI Insight distinguishes Ollama availability from a fallback explanation', () => {
    const freshness = {
      status: 'fresh',
      age_days: 1,
      gap_weeks: 0,
      requires_warning: false,
    }
    queryClient.setQueryData(keys.detail('/forecasts/options'), {
      units: [{ ...unit, history_start: '2022-09-01', history_end: '2026-10-28', history_weeks: 208, product_count: 1, data_freshness: freshness }],
      explanation_provider: 'ollama',
      explanation_runtime: { status: 'ready', model: 'qwen2.5:3b' },
    })
    queryClient.setQueryData(keys.list('/forecasts', { per_page: 10 }), {
      data: [{
        id: 1,
        operational_unit: unit,
        forecast_start: '2026-11-02',
        forecast_end: '2026-11-08',
        history_through: '2026-10-28',
        created_at: '2026-10-29T10:00:00+07:00',
        data_freshness: freshness,
        metrics: { wape: 20 },
        explanation_provider: 'ollama',
        explanation_status: 'fallback',
        explanation: { summary: 'Penjelasan sistem', risks: [], actions: [] },
        results: [],
      }],
    })
    const html = render(h(ForecastsPage))
    assert.match(html, /terhubung, model tersedia/)
    assert.match(html, /qwen2\.5:3b/)
    assert.match(html, /sistem \(fallback ollama\)/)
    assert.match(html, /Periksa ulang/)
  })
  session('superadmin', ['transaction.create'])
  const { ForecastDecisionModal } = await server.ssrLoadModule(
    '/src/features/forecasts/ForecastDecisionModal.tsx',
  )
  check('Forecast decision modal exposes correction and audit history', () => {
    const html = render(
      h(ForecastDecisionModal, {
        result: {
          id: 91,
          item: { id: 2, item_code: 'SKU-2', name: 'Produk Fixture', uom: 'pcs' },
          model: 'seasonal_52',
          history_weeks: 156,
          predicted_units: 12,
          available_quantity: 4,
          minimum_stock: 5,
          safety_stock_mode: 'CALCULATED',
          target_service_level: 95,
          calculated_safety_stock: 3,
          safety_stock: 3,
          supplier_lead_time_days: 7,
          order_pack_size: 6,
          incoming_quantity: 0,
          unrounded_restock: 11,
          recommended_restock: 12,
          decision_status: 'APPROVED',
          final_restock_quantity: 6,
          decision_reason: 'Kapasitas rak terbatas.',
          decided_by: user,
          decided_at: '2026-10-29T10:00:00+07:00',
          decision_history: [
            {
              id: 10,
              status: 'APPROVED',
              recommended_restock_snapshot: 12,
              final_restock_quantity: 6,
              reason: 'Kapasitas rak terbatas.',
              decided_by: user,
              decided_at: '2026-10-29T10:00:00+07:00',
            },
          ],
          order_by_date: '2026-10-29',
          order_status: 'due_today',
          mae: 2,
          rmse: 3,
          wape: 10,
        },
        onClose() {},
        onSaved() {},
      }),
    )
    assert.match(html, /Keputusan restock Produk Fixture/)
    assert.match(html, /Jumlah restock final/)
    assert.match(html, /Riwayat keputusan/)
    assert.match(html, /Kapasitas rak terbatas/)
  })
  check('Superadmin chooses transaction units from inline sidebar menus', () => {
    const html = render(h(Sidebar))
    assert.match(html, /POS, tampilkan pilihan unit/)
    assert.match(html, /Ticketing, tampilkan pilihan unit/)
    assert.doesNotMatch(html, /aria-haspopup="dialog"/)

    queryClient.setQueryData(
      keys.list('/operational-units', {
        is_active: 1,
        type: 'OUTLET',
        page: 1,
        per_page: 100,
      }),
      {
        data: [{ ...unit, name: 'Outlet Fixture', type: 'OUTLET' }],
        meta: {
          current_page: 1,
          last_page: 1,
          per_page: 100,
          total: 1,
          from: 1,
          to: 1,
        },
        links: { first: '', last: '', prev: null, next: null },
      },
    )

    const selectedHtml = render(h(Sidebar), ['/pos/7'])
    assert.match(selectedHtml, /POS, sembunyikan pilihan unit/)
    assert.match(selectedHtml, /class="nav-item has-unit-menu active"/)
    assert.match(selectedHtml, /aria-label="Unit POS"/)
    assert.match(selectedHtml, /href="\/pos\/7"/)
    assert.match(selectedHtml, /class="sidebar-unit-option active"/)
    assert.match(selectedHtml, /Outlet Fixture/)
    assert.match(selectedHtml, /Kasir Aktif/)
  })
  check('Superadmin POS no longer renders an operational unit picker', () => {
    const html = render(h(PosPage), ['/pos'])
    assert.match(html, /Pilih unit outlet melalui menu POS/)
    assert.doesNotMatch(html, /Unit operasional/)
  })
  check('Superadmin POS restores its selected unit from the route', () => {
    queryClient.setQueryData(keys.detail('/operational-units/7'), {
      ...unit,
      type: 'OUTLET',
      is_active: true,
    })
    const html = render(
      h(
        Routes,
        null,
        h(Route, {
          path: '/pos/:unitId',
          element: h(PosPage),
        }),
      ),
      ['/pos/7'],
    )
    assert.match(html, /Katalog Item/)
    assert.doesNotMatch(html, /Unit operasional/)
  })
  session('warehouse', ['transaction.create'])
  check('Warehouse cannot enter POS even with a mapped permission', () =>
    assert.match(guard('transaction.create', true), /Akses tidak diizinkan/),
  )
  check('Each operational account lands in its field workspace', () => {
    assert.equal(
      landingRouteFor({ role: { slug: 'admin' }, operational_unit: { type: 'OUTLET' } }),
      '/pos',
    )
    assert.equal(
      landingRouteFor({ role: { slug: 'admin' }, operational_unit: { type: 'TICKETING' } }),
      '/ticketing',
    )
    assert.equal(
      landingRouteFor({ role: { slug: 'warehouse' }, operational_unit: null }),
      '/inventory',
    )
    assert.equal(
      landingRouteFor({ role: { slug: 'superadmin' }, operational_unit: null }),
      '/dashboard',
    )
  })
  const serverError = {
    isAxiosError: true,
    response: {
      status: 500,
      data: {
        message: 'INTERNAL_STACK_SECRET',
        exception: 'INTERNAL_STACK_SECRET',
      },
    },
  }
  check('Server errors never expose stack or internal message', () =>
    assert.doesNotMatch(
      render(h(ErrorState, { error: serverError })),
      /INTERNAL_STACK_SECRET/,
    ),
  )
  check('Validation fields are preserved', () =>
    assert.equal(
      apiError({
        isAxiosError: true,
        response: {
          status: 422,
          data: { errors: { name: ['Nama wajib diisi.'] } },
        },
      }).fields.name[0],
      'Nama wajib diisi.',
    ),
  )
  check('Locked login shows the required OTP recovery instruction', () => {
    const message =
      'Login untuk username ini dikunci setelah tiga kali kata sandi salah. Gunakan Lupa password, minta kode OTP, lalu selesaikan reset kata sandi untuk membuka kembali akun.'
    const error = {
      isAxiosError: true,
      response: { status: 423, data: { message } },
    }

    assert.equal(apiError(error).message, message)
    assert.match(render(h(ErrorState, { error })), /minta kode OTP/)
  })
  check('Forbidden refetch hides previously cached table rows', () => {
    const html = render(
      h(ListContent, {
        query: {
          data: { data: [{ id: 1, secret: 'CACHED_PRIVATE_ROW' }] },
          isPending: false,
          isFetching: false,
          isPlaceholderData: false,
          error: { isAxiosError: true, response: { status: 403 } },
          refetch() {},
        },
        columns: [{ title: 'Data', render: (row) => row.secret }],
        rowKey: (row) => row.id,
        onPage() {},
      }),
    )
    assert.doesNotMatch(html, /CACHED_PRIVATE_ROW/)
    assert.match(html, /tidak memiliki izin/)
  })
  check('Rupiah formatting uses the API amount', () =>
    assert.match(formatCurrency('12500.50'), /12\.500,50/),
  )
  check('Transaction activity exposes its number and nominal total', () => {
    const presentation = presentActivity({
      id: 91,
      actor: null,
      action: 'TRANSACTION_PAID',
      subject_type: 'Transaction',
      subject_id: 23,
      metadata: {
        transaction_number: 'TRX-20261023-001',
        total: '125000.50',
        visitor_count: 3,
      },
      created_at: '2026-10-23T10:15:00+07:00',
    })

    assert.equal(presentation.title, 'Transaksi berhasil disimpan')
    assert.match(presentation.description, /TRX-20261023-001/)
    assert.match(presentation.description, /125\.000,50/)
  })
  check('Warehouse activity is described as a stock movement', () => {
    const presentation = presentActivity({
      id: 92,
      actor: null,
      action: 'WAREHOUSE_STOCK_OUT',
      subject_type: 'StockMovement',
      subject_id: 24,
      metadata: { item_id: 7, quantity: 12 },
      created_at: '2026-10-23T11:30:00+07:00',
    })

    assert.equal(presentation.title, 'Stok keluar berhasil dicatat')
    assert.equal(
      presentation.description,
      '12 unit • Barang #7 • Gudang utama',
    )
  })
  session('superadmin', [
    'user.view',
    'user.manage',
    'rbac.view',
    'operational-unit.view',
    'item.view',
    'item.manage',
    'inventory.view',
  ])
  const page = (data) => ({
    data,
    meta: {
      current_page: 1,
      last_page: 1,
      per_page: 100,
      total: data.length,
      from: 1,
      to: data.length,
    },
    links: {},
  })
  queryClient.setQueryData(
    keys.list('/operational-units', { per_page: 100 }),
    page([
      { ...unit, is_active: true },
      {
        ...unit,
        id: 8,
        code: 'ARCHIVED-UNIT',
        name: 'Archived Unit Fixture',
        is_active: false,
      },
    ]),
  )
  queryClient.setQueryData(
    keys.list('/operational-units/7/items', { per_page: 100 }),
    page([
      {
        id: 9,
        operational_unit: unit,
        item: {
          id: 2,
          item_code: 'UNIT-ITEM',
          name: 'Item per Unit Fixture',
          type: 'PRODUCT',
        },
        selling_price: '12500.00',
        minimum_stock: 5,
        is_active: true,
      },
    ]),
  )
  queryClient.setQueryData(
    keys.list('/operational-units/8/items', { per_page: 100 }),
    page([]),
  )
  const masterItemsPage = page([])
  masterItemsPage.meta.total = 32
  queryClient.setQueryData(
    keys.list('/items', { per_page: 1 }),
    masterItemsPage,
  )
  const activeItemsPage = page([])
  activeItemsPage.meta.total = 28
  queryClient.setQueryData(
    keys.list('/items', { per_page: 1, is_active: true }),
    activeItemsPage,
  )
  const itemListPage = page([
    {
      id: 21,
      item_code: 'PROD-TEST-ITEM',
      barcode: null,
      name: 'Item Master Fixture',
      type: 'PRODUCT',
      uom: 'pcs',
      visitor_factor: 0,
      is_active: true,
      created_at: null,
      updated_at: null,
    },
  ])
  Object.assign(itemListPage.meta, {
    per_page: 15,
    total: 32,
    last_page: 3,
  })
  queryClient.setQueryData(
    keys.list('/items', { page: 1, per_page: 15 }),
    itemListPage,
  )
  const { ItemsPage } = await server.ssrLoadModule(
    '/src/features/items/ItemsPage.tsx',
  )
  check('Master Item renders the referenced metrics, filters, and numbered table', () => {
    const html = render(h(ItemsPage))
    assert.match(html, /Ringkasan master item/)
    assert.match(html, /Total item/)
    assert.match(html, /Item Master Fixture/)
    assert.match(html, /PROD-TEST-ITEM/)
    assert.match(html, /Semua Jenis/)
    assert.match(html, /Navigasi halaman/)
    assert.match(html, /Tambah Item/)
  })
  const { UnitItemsView } = await server.ssrLoadModule(
    '/src/features/items/UnitItemsView.tsx',
  )
  check('Outlet view groups editable items below their parent outlet', () => {
    const html = render(
      h(UnitItemsView, { manage: true, onSaved() {} }),
    )
    assert.match(html, /Unit Fixture/)
    assert.match(html, /Item per Unit Fixture/)
    assert.match(html, /12\.500/)
    assert.match(html, /Tambah Item/)
    assert.match(html, /aria-label="Lihat rincian Item per Unit Fixture"/)
    assert.match(html, /Total item yang ada/i)
    assert.match(html, /Total item diplot/i)
    assert.match(html, />32</)
    assert.doesNotMatch(html, /Archived Unit Fixture/)
  })
  queryClient.setQueryData(
    keys.list('/roles', { per_page: 100 }),
    page([
      { id: 1, name: 'Admin', slug: 'admin', is_active: true, permissions: [] },
    ]),
  )
  for (const [path, name, props, expected] of [
    ['items/ItemForm', 'ItemForm', {}, 'Tambah Item'],
    [
      'operational-units/UnitForm',
      'UnitForm',
      { unit: { ...unit, is_active: true } },
      'Edit Outlet / Ticketing',
    ],
    [
      'operational-units/UnitItemForm',
      'UnitItemForm',
      {
        unitId: 7,
        item: {
          id: 9,
          item: {
            id: 2,
            item_code: 'HISTORICAL',
            name: 'Existing item',
            type: 'SERVICE',
          },
          selling_price: '12500.00',
          minimum_stock: 0,
          is_active: true,
        },
      },
      'Existing item',
    ],
    ['users/UserForm', 'UserForm', { user }, 'Kata sandi baru (opsional)'],
    ['inventory/StockForm', 'StockForm', { direction: 'out' }, 'Stok Keluar'],
    ['transfers/TransferForm', 'TransferForm', {}, 'Buat Transfer Stok'],
  ]) {
    const module = await server.ssrLoadModule(`/src/features/${path}.tsx`)
    check(`${name} renders with its required providers`, () =>
      assert.ok(
        render(
          h(module[name], { ...props, onClose() {}, onSaved() {} }),
        ).includes(expected),
      ),
    )
    if (name === 'ItemForm') {
      check('Item form shows generated code and helpful placeholders', () => {
        const html = render(h(module[name], { onClose() {}, onSaved() {} }))
        assert.match(html, /Dibuat otomatis saat disimpan/)
        assert.match(html, /Contoh: Air Mineral 600 ml/)
        assert.match(html, /disabled=""/)
      })
    }
  }
  const { UnitForm } = await server.ssrLoadModule(
    '/src/features/operational-units/UnitForm.tsx',
  )
  const { generateUnitCode } = await server.ssrLoadModule(
    '/src/features/operational-units/unitCode.ts',
  )
  check('New operational unit form explains the generated code format', () => {
    const html = render(
      h(UnitForm, { onClose() {}, onSaved() {} }),
    )
    assert.match(html, /Dibuat otomatis \(contoh: AB1234\)/)
    assert.match(html, /format 2 huruf dan 4 angka/)
    assert.match(html, /Masukkan nama outlet atau ticketing/)
    assert.match(html, /name="code"/)
    assert.match(html, /bg-slate-100/)
    assert.equal(generateUnitCode('Outlet 15', 1234), 'OU1234')
  })
  const { TransactionDetail } = await server.ssrLoadModule(
    '/src/features/transactions/TransactionDetail.tsx',
  )
  queryClient.setQueryData(keys.detail('/transactions/99'), {
    id: 99,
    transaction_number: 'TEST-HISTORY',
    operational_unit: unit,
    status: 'PAID',
    creator: user,
    payer: user,
    subtotal: '12345.67',
    total: '12345.67',
    visitor_count: 3,
    created_at: null,
    paid_at: null,
    items: [
      {
        id: 1,
        item: { name: 'Historical item' },
        quantity: 1,
        unit_price: '12345.67',
        subtotal: '12345.67',
        input_method: 'MANUAL',
      },
    ],
  })
  check(
    'Transaction detail renders historical server amounts and visitor count',
    () => {
      const html = render(h(TransactionDetail, { id: 99, onClose() {} }))
      assert.match(html, /12\.345,67/)
      assert.match(html, /TEST-HISTORY/)
      assert.match(html, /<dd>3<\/dd>/)
    },
  )
  const { DashboardPage } = await server.ssrLoadModule(
    '/src/features/dashboard/pages/DashboardPage.tsx',
  )
  for (const role of ['admin', 'warehouse', 'superadmin']) {
    session(role, ['dashboard.view'])
    check(`Dashboard renders role ${role} without invented totals`, () => {
      const html = render(h(DashboardPage))
      assert.match(html, /Akses Cepat/)
      assert.doesNotMatch(html, /NaN|undefined|Rp/)
    })
  }
  session('warehouse', ['dashboard.view'])
  const { ProfilePage } = await server.ssrLoadModule(
    '/src/features/account/ProfilePage.tsx',
  )
  check('Profile page renders the signed-in identity and assignments', () => {
    const html = render(h(ProfilePage))
    assert.match(html, /Akses aktif/)
    assert.match(html, /fixture@example\.test/)
    assert.match(html, /Seluruh role dan unit/)
  })
  const { AccountSettingsPage } = await server.ssrLoadModule(
    '/src/features/account/AccountSettingsPage.tsx',
  )
  check('Account settings exposes profile and password forms', () => {
    const html = render(h(AccountSettingsPage))
    assert.match(html, /Informasi akun/)
    assert.match(html, /Kata sandi saat ini/)
    assert.match(html, /Perbarui kata sandi/)
  })
  const { MyActivityPage } = await server.ssrLoadModule(
    '/src/features/account/MyActivityPage.tsx',
  )
  check('Personal activity page renders without global audit permission', () => {
    const html = render(h(MyActivityPage))
    assert.match(html, /Aksi/)
    assert.match(html, /Jenis subjek/)
  })
  const { api, sessionApi } = await server.ssrLoadModule('/src/lib/api.ts')
  const originalAdapter = api.defaults.adapter
  const requests = []
  const cart = {
    id: 88,
    status: 'CART',
    transaction_number: 'TEST-CART',
    operational_unit: unit,
    creator: user,
    payer: null,
    subtotal: '7000.00',
    total: '7000.00',
    visitor_count: 4,
    items: [
      {
        id: 6,
        item: { id: 2, name: 'Fixture item' },
        quantity: 2,
        unit_price: '3500.00',
        subtotal: '7000.00',
        input_method: 'MANUAL',
      },
    ],
  }
  let responseCart = cart
  let conflict = false
  api.defaults.adapter = async (config) => {
    requests.push({
      method: config.method,
      url: config.url,
      data: config.data ? JSON.parse(config.data) : undefined,
    })
    if (conflict && config.url.endsWith('/checkout'))
      throw {
        isAxiosError: true,
        response: {
          status: 409,
          data: { message: 'Insufficient available stock.' },
        },
      }
    return {
      data: { data: responseCart },
      status: 200,
      statusText: 'OK',
      headers: {},
      config,
    }
  }
  function captureCart() {
    let captured
    function Probe() {
      // eslint-disable-next-line react/globals -- SSR-only hook harness; no mounted UI or shared production state.
      captured = usePosCart(unit.id)
      return null
    }
    render(h(Probe))
    return captured
  }
  try {
    session('admin', ['transaction.create', 'transaction.view'])
    queryClient.setQueryData(keys.cart(user.id, unit.id), cart)
    const pos = captureCart()
    await pos.newCart.mutateAsync()
    check('Admin cart resolution omits prohibited operational_unit_id', () =>
      assert.deepEqual(requests.at(-1).data, {}),
    )
    await pos.action.mutateAsync({ type: 'add', item_id: 2 })
    check('Manual input uses the backend MANUAL payload', () =>
      assert.deepEqual(requests.at(-1).data, {
        input_method: 'MANUAL',
        item_id: 2,
        quantity: 1,
      }),
    )
    await pos.action.mutateAsync({ type: 'quantity', id: 6, quantity: 3 })
    check('Quantity updates target the transaction item with PATCH', () =>
      assert.deepEqual(requests.at(-1), {
        method: 'patch',
        url: '/transactions/88/items/6',
        data: { quantity: 3 },
      }),
    )
    await pos.action.mutateAsync({ type: 'remove', id: 6 })
    check('Remove targets the transaction item with DELETE', () =>
      assert.equal(requests.at(-1).method, 'delete'),
    )
    responseCart = {
      ...cart,
      subtotal: '0.00',
      total: '0.00',
      visitor_count: 0,
      items: [],
    }
    await pos.action.mutateAsync({ type: 'clear' })
    check('Clear removes every cart item with one DELETE request', () =>
      assert.deepEqual(requests.at(-1), {
        method: 'delete',
        url: '/transactions/88/items',
        data: undefined,
      }),
    )
    responseCart = cart
    queryClient.setQueryData(keys.cart(user.id, unit.id), cart)
    conflict = true
    await assert.rejects(pos.action.mutateAsync({ type: 'checkout' }))
    check('Checkout conflict preserves cart identity and lines', () =>
      assert.deepEqual(
        queryClient.getQueryData(keys.cart(user.id, unit.id)),
        cart,
      ),
    )
    conflict = false
    responseCart = {
      ...cart,
      status: 'PAID',
      total: '9000.00',
      visitor_count: 8,
    }
    await pos.action.mutateAsync({ type: 'checkout' })
    check(
      'Checkout uses the final server status, amount, and visitor count',
      () =>
        assert.deepEqual(
          queryClient.getQueryData(keys.cart(user.id, unit.id)),
          responseCart,
        ),
    )
    const count = requests.length
    await assert.rejects(pos.action.mutateAsync({ type: 'add', item_id: 2 }))
    check('Paid cart cannot issue item mutations', () =>
      assert.equal(requests.length, count),
    )
    session('superadmin', ['transaction.create', 'transaction.view'])
    responseCart = cart
    const superadminPos = captureCart()
    await superadminPos.newCart.mutateAsync()
    check(
      'Superadmin cart resolution includes the selected operational unit',
      () =>
        assert.deepEqual(requests.at(-1).data, {
          operational_unit_id: unit.id,
        }),
    )
  } finally {
    api.defaults.adapter = originalAdapter
  }
  const { useAuth } = await server.ssrLoadModule(
    '/src/features/auth/useAuth.ts',
  )
  const originalSessionAdapter = sessionApi.defaults.adapter
  const authRequests = []
  const authResponse = { ...user, permissions: ['dashboard.view'] }
  sessionApi.defaults.adapter = async (config) => {
    authRequests.push(config.url)
    return {
      data: null,
      status: 204,
      statusText: 'No Content',
      headers: {},
      config,
    }
  }
  api.defaults.adapter = async (config) => {
    authRequests.push(config.url)
    return {
      data: { data: authResponse },
      status: 200,
      statusText: 'OK',
      headers: {},
      config,
    }
  }
  queryClient.setQueryData(keys.auth, null)
  queryClient.setQueryData(['/users', 'private'], { confidential: true })
  const authObserver = new QueryObserver(queryClient, {
    queryKey: keys.auth,
    enabled: false,
  })
  const unsubscribe = authObserver.subscribe(() => {})
  let unsubscribePrivate = () => {}
  let authActions
  function AuthProbe() {
    // eslint-disable-next-line react/globals -- SSR-only harness for mutation callbacks.
    authActions = useAuth()
    return null
  }
  render(h(AuthProbe))
  try {
    await authActions.login({
      username: user.username,
      password: 'FixturePassword123',
    })
    check(
      'Login uses the CSRF cookie followed by the login request',
      () =>
        assert.deepEqual(authRequests, [
          '/sanctum/csrf-cookie',
          '/auth/login',
        ]),
    )
    check(
      'Login notifies existing auth observers and removes previous private cache',
      () => {
        assert.deepEqual(authObserver.getCurrentResult().data, authResponse)
        assert.equal(queryClient.getQueryData(['/users', 'private']), undefined)
      },
    )
    const movementKey = keys.list('/inventory/movements')
    const previousAssignmentPage = page([
      { id: 1, location: { name: 'PREVIOUS_ASSIGNMENT_DATA' } },
    ])
    const activeAssignmentPage = page([])
    queryClient.setQueryData(movementKey, previousAssignmentPage)
    const privateObserver = new QueryObserver(queryClient, {
      queryKey: movementKey,
      queryFn: async () => activeAssignmentPage,
      staleTime: Infinity,
    })
    unsubscribePrivate = privateObserver.subscribe(() => {})
    await authActions.selectAssignment(2)
    check('Assignment switch resets and refetches the active private screen', () => {
      assert.deepEqual(privateObserver.getCurrentResult().data, activeAssignmentPage)
      assert.doesNotMatch(
        JSON.stringify(privateObserver.getCurrentResult().data),
        /PREVIOUS_ASSIGNMENT_DATA/,
      )
    })
    await authActions.logout()
    check('Logout notifies mounted guards with an unauthenticated state', () =>
      assert.equal(authObserver.getCurrentResult().data, null),
    )
    queryClient.setQueryData(keys.auth, authResponse)
    queryClient.setQueryData(['/users', 'private'], { confidential: true })
    api.defaults.adapter = async () => {
      throw {
        isAxiosError: true,
        response: { status: 401, data: { message: 'Unauthenticated.' } },
      }
    }
    await assert.rejects(api.get('/inventory/warehouse'))
    check(
      'Authenticated 401 clears private data and notifies auth guards',
      () => {
        assert.equal(authObserver.getCurrentResult().data, null)
        assert.equal(queryClient.getQueryData(['/users', 'private']), undefined)
      },
    )
  } finally {
    unsubscribePrivate()
    unsubscribe()
    api.defaults.adapter = originalAdapter
    sessionApi.defaults.adapter = originalSessionAdapter
  }
  queryClient.clear()
  console.log(
    `${checks} checks passed. SSR checks only; no browser interaction was performed.`,
  )
} finally {
  await server.close()
  // Vite 8 can retain a closed, non-listening internal server on Windows.
  // It has no work left, so do not let that handle keep this one-shot check alive.
  for (const handle of process._getActiveHandles()) {
    if (handle.constructor?.name === 'Server' && handle.listening === false)
      handle.unref()
  }
}

// Vite 8 may retain internal timers on Windows after close(). This line is only
// reached when every awaited check and cleanup step above completed successfully.
process.stdout.write('', () => process.exit(0))
