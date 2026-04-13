<!DOCTYPE html>
<html lang="en" style="height:100%">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sepio Inspector · {{ config('app.name') }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link
        href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;600&family=Inter:wght@400;500;600;700&display=swap"
        rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/react@18/umd/react.production.min.js" crossorigin></script>
    <script src="https://unpkg.com/react-dom@18/umd/react-dom.production.min.js" crossorigin></script>
    <script src="https://unpkg.com/@babel/standalone/babel.min.js"></script>
    <style>
        *, *::before, *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        html, body, #root {
            height: 100%;
        }

        body {
            font-family: 'Inter', system-ui, sans-serif;
            background: #0f172a;
            color: #f1f5f9;
        }

        code, pre, textarea, .mono {
            font-family: 'JetBrains Mono', 'Fira Code', monospace;
        }

        .jk {
            color: #67e8f9;
            font-weight: 500;
        }

        .js {
            color: #86efac;
        }

        .jn {
            color: #fcd34d;
        }

        .jb {
            color: #c084fc;
        }

        .jz {
            color: #94a3b8;
        }

        ::-webkit-scrollbar {
            width: 6px;
            height: 6px;
        }

        ::-webkit-scrollbar-thumb {
            background: #475569;
            border-radius: 3px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: #64748b;
        }

        ::-webkit-scrollbar-track {
            background: #1e293b;
        }

        textarea {
            resize: none;
            outline: none;
        }

        select {
            outline: none;
        }

        *:focus-visible {
            outline: 2px solid #6366f1;
            outline-offset: 2px;
        }

        .ew-resize {
            cursor: ew-resize;
        }
    </style>
    <script>window.__SEPIO_BASE = "{{ config('sepio.base_url') }}";</script>
</head>
<body>
<div id="root"></div>

@verbatim
    <script type="text/babel">
        const {useState, useEffect, useRef, useCallback} = React;

        const BASE = window.__SEPIO_BASE;
        const API = '/api/v1';
        const T_KEY = 'sepio_insp_tok';

        /* ── Auth helpers ─────────────────────────────────────────────────────────── */
        const getToken = () => localStorage.getItem(T_KEY);
        const saveToken = (t) => t ? localStorage.setItem(T_KEY, t) : localStorage.removeItem(T_KEY);

        const apiFetch = (url, opts = {}) => {
            const token = getToken();
            const isForm = opts.body instanceof FormData;
            const headers = {
                Accept: 'application/json',
                ...(!isForm ? {'Content-Type': 'application/json'} : {}),
                ...(token ? {Authorization: `Bearer ${token}`} : {}),
                ...(opts.headers || {}),
            };
            return fetch(url, {...opts, headers}).then(r => {
                if (r.status === 401) window.dispatchEvent(new Event('sepio:401'));
                return r;
            });
        };

        /* ── Endpoint catalogue ───────────────────────────────────────────────────── */
        const fmtDate = (d) => d.toISOString().replace('T', ' ').slice(0, 23);

        const GROUPS = [
            {
                id: 'master', label: 'Master Data', dot: '#06b6d4',
                eps: [
                    {
                        id: 'ports',
                        label: 'Customs Port List',
                        method: 'GET',
                        path: '/customsExecutive/customsportlist',
                        auth: false,
                        needsCust: false,
                        note: 'Returns all Sepio customs seaports. No authentication required.',
                        payload: () => ({})
                    },
                    {
                        id: 'icds',
                        label: 'ICD List',
                        method: 'GET',
                        path: '/customsExecutive/customsicdlist',
                        auth: false,
                        needsCust: false,
                        note: 'Returns all inland container depot locations. No auth required.',
                        payload: () => ({})
                    },
                    {
                        id: 'cfs',
                        label: 'CFS Location List',
                        method: 'GET',
                        path: '/companyAdmin/cfslocationlist',
                        auth: false,
                        needsCust: false,
                        note: 'Returns all container freight station locations. No auth required.',
                        payload: () => ({})
                    },
                ]
            },
            {
                id: 'auth', label: 'Authentication', dot: '#f59e0b',
                eps: [
                    {
                        id: 'refresh',
                        label: 'Refresh Token',
                        method: 'POST',
                        path: '__internal__/refresh-token',
                        auth: false,
                        needsCust: true,
                        internal: true,
                        note: 'Force-refreshes the Sepio JWT for the selected customer via our backend. Returns truncated token + expiry.',
                        payload: () => ({})
                    },
                ]
            },
            {
                id: 'reg', label: 'Registration', dot: '#8b5cf6',
                eps: [
                    {
                        id: 'reg-co',
                        label: 'Register Company',
                        method: 'POST',
                        path: '/registrationModule/registercompany',
                        auth: false,
                        needsCust: true,
                        note: 'Registers a new company on Sepio (unauthenticated).',
                        payload: (c) => ({
                            companydetailsInfo: {
                                companyName: c?.company_name || '',
                                IEC: c?.iec_number || '',
                                sealRequest: '1000',
                                port: '',
                                icd: '',
                                cfsLocation: '',
                                chaUser: '',
                                chaId: '',
                                distributorId: 'D100247',
                                sepioURL: 'sepio/companies'
                            },
                            primaryContactInfo: {
                                fName: c?.first_name || '',
                                lName: c?.last_name || '',
                                email: c?.primary_contact_email || c?.email || '',
                                contactNo: '',
                                password: 'Sepio@123',
                                conpassword: 'Sepio@123',
                                isAdmin: true
                            },
                            register_from_type: 'ILGIC'
                        })
                    },
                    {
                        id: 'upd-addr',
                        label: 'Update Address',
                        method: 'POST',
                        path: '/registrationModule/updateaddress',
                        auth: true,
                        needsCust: true,
                        note: 'Sync billing + shipping address for the company. State must be UPPER_CASE.',
                        payload: (c) => ({
                            createdBy: c?.primary_contact_email || c?.email || '',
                            companyId: c?.sepio_company_id || '',
                            billingAddressInfo: {
                                billAddresses: [{
                                    billingCompanyName: c?.company_name || '',
                                    address: '',
                                    landmark: '',
                                    zipcode: '',
                                    city: '',
                                    state: '',
                                    gstno: c?.gst_number || ''
                                }]
                            },
                            shippingAddressInfo: {
                                addresses: [{
                                    address: '',
                                    landmark: '',
                                    city: '',
                                    state: '',
                                    zipcode: ''
                                }]
                            },
                            fclFlag: 1,
                            cfsFlag: 1,
                            warehouseFlag: 0
                        })
                    },
                ]
            },
            {
                id: 'kyc', label: 'KYC Documents', dot: '#10b981',
                eps: [
                    {
                        id: 'kyc-gst',
                        label: 'Add KYC (GST / IEC / PAN)',
                        method: 'POST',
                        path: '/kycData/addkyc',
                        auth: true,
                        needsCust: true,
                        file: true,
                        note: 'Upload GST, IEC or PAN. Adjust documentType (gstCopy | iecCopy | panCopy) and documentName (GST | IEC | PAN) before sending.',
                        payload: (c) => ({
                            companyId: c?.sepio_company_id || '',
                            IEC: c?.iec_number || '',
                            dateNow: Date.now(),
                            documentType: 'gstCopy',
                            documentExtension: 'pdf',
                            documentName: 'GST',
                            fclFlag: 1,
                            cfsFlag: 1,
                            warehouseFlag: 0
                        })
                    },
                    {
                        id: 'kyc-ss',
                        label: 'Self-Stuffing Certificate',
                        method: 'POST',
                        path: '/kycData/addselfstuffing',
                        auth: true,
                        needsCust: true,
                        file: true,
                        note: 'Upload self-stuffing certificate. documentType: selfCopy, documentName: CHEQ.',
                        payload: (c) => ({
                            companyId: c?.sepio_company_id || '',
                            IEC: c?.iec_number || '',
                            dateNow: Date.now(),
                            documentType: 'selfCopy',
                            documentExtension: 'pdf',
                            documentName: 'CHEQ',
                            fclFlag: 1,
                            cfsFlag: 1,
                            warehouseFlag: 0
                        })
                    },
                    {
                        id: 'kyc-cor',
                        label: 'Certificate of Registration',
                        method: 'POST',
                        path: '/kycData/addCORdoc',
                        auth: true,
                        needsCust: true,
                        file: true,
                        note: 'Upload CFS certificate of registration. documentName: CFSREGISTRATION.',
                        payload: (c) => ({
                            companyId: c?.sepio_company_id || '',
                            IEC: c?.iec_number || '',
                            dateNow: Date.now(),
                            documentType: 'certificateofRegistrationCompnay',
                            documentExtension: 'pdf',
                            documentName: 'CFSREGISTRATION',
                            fclFlag: 1,
                            cfsFlag: 1,
                            warehouseFlag: 0
                        })
                    },
                    {
                        id: 'kyc-cha',
                        label: 'CHA Auth Letter',
                        method: 'POST',
                        path: '/kycData/addchaAuthLetter',
                        auth: true,
                        needsCust: true,
                        file: true,
                        note: 'Upload CHA authorization letter.',
                        payload: (c) => ({
                            companyId: c?.sepio_company_id || '',
                            dateNow: Date.now(),
                            documentExtension: 'pdf',
                            documentName: 'CHA'
                        })
                    },
                ]
            },
            {
                id: 'verify', label: 'Verification', dot: '#06b6d4',
                eps: [
                    {
                        id: 'doc-verify',
                        label: 'Document Verification Status',
                        method: 'POST',
                        path: '/api/v1/document/verification/status/pull',
                        auth: true,
                        needsCust: true,
                        note: 'Poll KYC verification status. Response: VERIFIED | PENDING | REJECTED.',
                        payload: (c) => ({requestId: 'ILGIC-' + Date.now(), companyIds: [c?.sepio_company_id || '']})
                    },
                ]
            },
            {
                id: 'preorder', label: 'Pre-Order', dot: '#f97316',
                eps: [
                    {
                        id: 'ship-list',
                        label: 'Shipping Address List',
                        method: 'GET',
                        path: '/registrationModule/getshippinglist',
                        auth: true,
                        needsCust: true,
                        note: 'Get all registered shipping addresses. Use addressId when placing an order.',
                        payload: (c) => ({companyId: c?.sepio_company_id || ''})
                    },
                    {
                        id: 'bill-list',
                        label: 'Billing Address List',
                        method: 'GET',
                        path: '/registrationModule/getbillinglistnew',
                        auth: true,
                        needsCust: true,
                        note: 'Get all registered billing addresses. Use addressId when placing an order.',
                        payload: (c) => ({companyId: c?.sepio_company_id || ''})
                    },
                    {
                        id: 'co-details',
                        label: 'Company Details (Pricing)',
                        method: 'GET',
                        path: '/companyadmin/companydetails',
                        auth: true,
                        needsCust: true,
                        note: 'Returns unitprice, freight, tax (18%), and paymentterms for order calculation.',
                        payload: (c) => ({companyId: c?.sepio_company_id || ''})
                    },
                    {
                        id: 'co-profile',
                        label: 'Company Full Profile',
                        method: 'GET',
                        path: '/registrationModule/getpercompanylist',
                        auth: true,
                        needsCust: true,
                        note: 'Full profile including KYC status, ports, ICDs, and uploaded doc filenames.',
                        payload: (c) => ({companyId: c?.sepio_company_id || ''})
                    },
                ]
            },
            {
                id: 'orders', label: 'Orders', dot: '#f43f5e',
                eps: [
                    {
                        id: 'place-order',
                        label: 'Place Order',
                        method: 'POST',
                        path: '/companyadmin/placedorder',
                        auth: true,
                        needsCust: true,
                        note: 'Place a seal order. Get shippingAddressId + billingAddressId from the Address List endpoints first.',
                        payload: (c) => ({
                            sealType: 'bolt',
                            companyId: c?.sepio_company_id || '',
                            shippingAddressId: '',
                            billingAddressId: '',
                            createdBy: c?.primary_contact_email || c?.email || '',
                            orderType: 'credit',
                            sealCount: 10,
                            orderPorts: [],
                            unitprice: 299,
                            totalprice: 2990,
                            freight: 200,
                            tax: 574.2,
                            grandtotal: 3764.2,
                            creditPeriod: 30,
                            distributorId: 'D100247',
                            deliveryId: '1',
                            discrate: 0,
                            purchaseOrderNumber: null,
                            isSezUser: 0,
                            sepioURL: 'sepio/orders',
                            reqId: '',
                            totalRoundOff: 0,
                            shippingInfo: {address: '', city: '', landmark: '', state: '', zip: ''},
                            billingInfo: {
                                billingCompanyName: c?.company_name || '',
                                gstno: c?.gst_number || '',
                                address: '',
                                city: '',
                                landmark: '',
                                state: '',
                                zip: ''
                            }
                        })
                    },
                    {
                        id: 'order-list',
                        label: 'Order List',
                        method: 'GET',
                        path: '/companyAdmin/listplacedorder',
                        auth: true,
                        needsCust: true,
                        note: 'Paginated list of all orders (0-indexed pageNo).',
                        payload: (c) => ({companyId: c?.sepio_company_id || '', pageNo: 0})
                    },
                    {
                        id: 'filter-orders',
                        label: 'Filter Orders',
                        method: 'GET',
                        path: '/companyAdmin/filterorderlistcompany',
                        auth: true,
                        needsCust: true,
                        note: 'Filter by orderId, invoiceNo, or dispatch date range.',
                        payload: (c) => ({
                            companyId: c?.sepio_company_id || '',
                            pageNo: 0,
                            orderId: '',
                            invoiceNo: '',
                            dispatchDateFrom: '',
                            dispatchDateTo: ''
                        })
                    },
                ]
            },
            {
                id: 'seals', label: 'Seals & Tracking', dot: '#14b8a6',
                eps: [
                    {
                        id: 'alloc-pull',
                        label: 'Seal Allocation Pull',
                        method: 'POST',
                        path: '/api/v1/seal/seal-allocation/pull',
                        auth: true,
                        needsCust: true,
                        note: 'Pull seal ranges allocated to recent orders. Max 2-day window.',
                        payload: (c) => ({
                            company_id: c?.sepio_company_id || '',
                            start_datetime: fmtDate(new Date(Date.now() - 2 * 86400000)),
                            end_datetime: fmtDate(new Date())
                        })
                    },
                    {
                        id: 'pre-install',
                        label: 'Pre-Install Seal Check',
                        method: 'POST',
                        path: '/installationUser/singleinstallsealcheck',
                        auth: true,
                        needsCust: true,
                        note: 'Verify a bolt seal is available before installation.',
                        payload: (c) => ({sealString: 'SPPL', sealNo: '', companyId: c?.sepio_company_id || ''})
                    },
                    {
                        id: 'scan-hist',
                        label: 'Seal Scan History',
                        method: 'POST',
                        path: '/api/v1/seal/scan-history/pull',
                        auth: true,
                        needsCust: false,
                        note: 'Pull scan history for a seal from a given start datetime.',
                        payload: () => ({seal_no: '', from_datetime: fmtDate(new Date(Date.now() - 86400000))})
                    },
                ]
            },
        ];

        /* ── Helpers ──────────────────────────────────────────────────────────────── */
        const fmtJson = (s) => {
            try {
                return JSON.stringify(JSON.parse(s), null, 2);
            } catch {
                return s;
            }
        };

        const colorJson = (s) =>
            s.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
                .replace(/("(?:\\u[\dA-Fa-f]{4}|\\[^u]|[^\\"])*"(\s*:)?|\b(?:true|false|null)\b|-?\d+(?:\.\d*)?(?:[eE][+-]?\d+)?)/g, (m) => {
                    if (/^"/.test(m)) return `<span class="${/:$/.test(m) ? 'jk' : 'js'}">${m}</span>`;
                    if (/true|false/.test(m)) return `<span class="jb">${m}</span>`;
                    if (/null/.test(m)) return `<span class="jz">${m}</span>`;
                    return `<span class="jn">${m}</span>`;
                });

        const METHOD_CLS = {
            GET: 'text-emerald-400 bg-emerald-500/20 border border-emerald-500/30',
            POST: 'text-sky-400 bg-sky-500/20 border border-sky-500/30',
        };

        const statusCls = (s) => {
            if (!s) return 'text-slate-400 bg-slate-800';
            if (s >= 200 && s < 300) return 'text-emerald-400 bg-emerald-500/20 border border-emerald-500/30';
            if (s >= 400 && s < 500) return 'text-amber-400 bg-amber-500/20 border border-amber-500/30';
            return 'text-red-400 bg-red-500/20 border border-red-500/30';
        };

        const initials = (name = '') =>
            name.split(' ').map(w => w[0]).join('').toUpperCase().slice(0, 2);

        /* ══════════════════════════════════════════════════════════════════════════
           AUTH SCREENS
        ══════════════════════════════════════════════════════════════════════════ */

        function LoginScreen({onLogin}) {
            const [email, setEmail] = useState('');
            const [password, setPassword] = useState('');
            const [showPw, setShowPw] = useState(false);
            const [loading, setLoading] = useState(false);
            const [error, setError] = useState('');

            const submit = async () => {
                if (!email || !password) {
                    setError('Email and password are required.');
                    return;
                }
                setLoading(true);
                setError('');
                try {
                    const r = await fetch(`${API}/auth/login`, {
                        method: 'POST',
                        headers: {'Content-Type': 'application/json', Accept: 'application/json'},
                        body: JSON.stringify({email, password}),
                    });
                    const data = await r.json();
                    if (!r.ok) {
                        setError(data.message || 'Invalid credentials.');
                    } else {
                        saveToken(data.token);
                        onLogin(data.token, data.user);
                    }
                } catch {
                    setError('Network error — is the server running?');
                } finally {
                    setLoading(false);
                }
            };

            const onKey = (e) => e.key === 'Enter' && submit();

            return (
                <div
                    style={{ display: 'flex', alignItems: 'center', justifyContent: 'center', minHeight: '100vh', background: '#0f172a' }}>
                    <div
                        style={{ width: 380 }} className="bg-slate-900 border border-slate-700 rounded-2xl shadow-2xl overflow-hidden">
                        {/* Card header */}
                        <div className="px-8 pt-8 pb-6 border-b border-slate-700/60">
                            <div className="flex items-center gap-3 mb-1">
                                <div
                                    className="w-9 h-9 rounded-xl bg-gradient-to-br from-indigo-500 to-purple-600 flex items-center justify-center shadow-lg">
                                    <svg className="w-5 h-5 text-white" fill="none" viewBox="0 0 24 24"
                                         stroke="currentColor">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2.5}
                                              d="M13 10V3L4 14h7v7l9-11h-7z"/>
                                    </svg>
                                </div>
                                <div>
                                    <p className="text-base font-bold text-slate-100 leading-tight">Sepio Inspector</p>
                                    <p className="text-xs text-slate-500">Developer Tool</p>
                                </div>
                            </div>
                        </div>

                        {/* Form */}
                        <div className="px-8 py-6 space-y-4">
                            {error && (
                                <div
                                    className="flex items-start gap-2.5 bg-red-500/10 border border-red-500/30 rounded-lg px-3.5 py-3">
                                    <svg className="w-4 h-4 text-red-400 mt-0.5 shrink-0" fill="currentColor"
                                         viewBox="0 0 20 20">
                                        <path fillRule="evenodd"
                                              d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z"
                                              clipRule="evenodd"/>
                                    </svg>
                                    <p className="text-sm text-red-300">{error}</p>
                                </div>
                            )}

                            <div>
                                <label
                                    className="block text-xs font-semibold text-slate-400 mb-1.5 tracking-wide uppercase">Email</label>
                                <input
                                    type="email" value={email} onChange={e => setEmail(e.target.value)}
                                    onKeyDown={onKey}
                                    placeholder="you@company.com" autoFocus
                                    className="w-full bg-slate-800 border border-slate-600 rounded-lg text-sm text-slate-100 px-3.5 py-2.5 placeholder:text-slate-600 focus:border-indigo-500 transition-colors outline-none"
                                    style={{ fontFamily: 'Inter, sans-serif' }}
                                />
                            </div>

                            <div>
                                <label
                                    className="block text-xs font-semibold text-slate-400 mb-1.5 tracking-wide uppercase">Password</label>
                                <div className="relative">
                                    <input
                                        type={showPw ? 'text' : 'password'} value={password}
                                        onChange={e => setPassword(e.target.value)} onKeyDown={onKey}
                                        placeholder="••••••••"
                                        className="w-full bg-slate-800 border border-slate-600 rounded-lg text-sm text-slate-100 px-3.5 py-2.5 pr-10 placeholder:text-slate-600 focus:border-indigo-500 transition-colors outline-none"
                                        style={{ fontFamily: 'Inter, sans-serif' }}
                                    />
                                    <button
                                        onClick={() => setShowPw(p => !p)} tabIndex={-1}
                                        className="absolute right-3 top-1/2 -translate-y-1/2 text-slate-500 hover:text-slate-300 transition-colors"
                                    >
                                        {showPw
                                            ? <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24"
                                                   stroke="currentColor">
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2}
                                                      d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l18 18"/>
                                            </svg>
                                            : <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24"
                                                   stroke="currentColor">
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2}
                                                      d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2}
                                                      d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                            </svg>
                                        }
                                    </button>
                                </div>
                            </div>

                            <button
                                onClick={submit} disabled={loading}
                                className={`w-full flex items-center justify-center gap-2 py-2.5 rounded-lg text-sm font-semibold transition-all mt-2 ${
                                    loading ? 'bg-indigo-700 text-indigo-300 cursor-not-allowed' : 'bg-indigo-600 hover:bg-indigo-500 text-white shadow-lg shadow-indigo-900/40'
                                }`}
                            >
                                {loading
                                    ? <>
                                        <svg className="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                            <circle cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4"
                                                    className="opacity-20"/>
                                            <path fill="currentColor" d="M4 12a8 8 0 018-8V0C5.4 0 0 5.4 0 12h4z"/>
                                        </svg>
                                        Signing in…</>
                                    : 'Sign In →'
                                }
                            </button>
                        </div>

                        <div className="px-8 py-3 bg-slate-950/40 border-t border-slate-700/60">
                            <p className="mono text-xs text-slate-600 text-center">⚠ Development tool ·
                                Non-production</p>
                        </div>
                    </div>
                </div>
            );
        }

        function LoadingScreen() {
            return (
                <div
                    style={{ display: 'flex', alignItems: 'center', justifyContent: 'center', height: '100vh', background: '#0f172a' }}>
                    <svg className="w-8 h-8 animate-spin text-indigo-500" fill="none" viewBox="0 0 24 24">
                        <circle cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" className="opacity-20"/>
                        <path fill="currentColor" d="M4 12a8 8 0 018-8V0C5.4 0 0 5.4 0 12h4z"/>
                    </svg>
                </div>
            );
        }

        function PermissionDenied({onLogout}) {
            return (
                <div
                    style={{ display: 'flex', flexDirection: 'column', alignItems: 'center', justifyContent: 'center', height: '100vh', background: '#0f172a', gap: 16 }}>
                    <div
                        className="w-14 h-14 rounded-2xl bg-red-500/10 border border-red-500/30 flex items-center justify-center">
                        <svg className="w-7 h-7 text-red-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2}
                                  d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                        </svg>
                    </div>
                    <div className="text-center">
                        <p className="text-slate-100 font-semibold text-base">Access Denied</p>
                        <p className="text-slate-500 text-sm mt-1">You do not have permission to <span
                            className="mono text-slate-400">Inspect Sepio APIs</span>.</p>
                    </div>
                    <button onClick={onLogout}
                            className="mt-2 px-5 py-2 bg-slate-800 hover:bg-slate-700 border border-slate-600 text-slate-200 text-sm font-medium rounded-lg transition-colors">
                        Sign Out
                    </button>
                </div>
            );
        }

        /* ══════════════════════════════════════════════════════════════════════════
           CUSTOMER SELECTOR  (searchable combobox, status in options)
        ══════════════════════════════════════════════════════════════════════════ */

        const STATUS_META = (s = '') => {
            const v = s.toLowerCase();
            if (v.includes('complet') || v.includes('verif') || v.includes('active'))
                return {dot: '#34d399', label: s, pill: 'text-emerald-400 bg-emerald-500/10 border-emerald-500/30'};
            if (v.includes('pending') || v.includes('review'))
                return {dot: '#fbbf24', label: s, pill: 'text-amber-400 bg-amber-500/10 border-amber-500/30'};
            if (v.includes('reject') || v.includes('fail'))
                return {dot: '#f87171', label: s, pill: 'text-red-400 bg-red-500/10 border-red-500/30'};
            return {dot: '#64748b', label: s || 'unknown', pill: 'text-slate-400 bg-slate-700/50 border-slate-600'};
        };

        function CustomerSelector({customers, custId, onCustChange}) {
            const [query, setQuery] = useState('');
            const [open, setOpen] = useState(false);
            const [infoOpen, setInfoOpen] = useState(false);
            const wrapRef = useRef(null);
            const inputRef = useRef(null);

            const cust = customers.find(c => String(c.id) === String(custId)) || null;

            const filtered = query.trim()
                ? customers.filter(c => c.company_name.toLowerCase().includes(query.toLowerCase()))
                : customers;

            // Close on outside click
            useEffect(() => {
                if (!open && !infoOpen) return;
                const h = (e) => {
                    if (wrapRef.current && !wrapRef.current.contains(e.target)) {
                        setOpen(false);
                        setInfoOpen(false);
                    }
                };
                document.addEventListener('mousedown', h);
                return () => document.removeEventListener('mousedown', h);
            }, [open, infoOpen]);

            const openDropdown = () => {
                setOpen(true);
                setQuery('');
                setTimeout(() => inputRef.current?.focus(), 0);
            };

            const select = (id) => {
                onCustChange(id);
                setOpen(false);
                setQuery('');
            };

            const clear = (e) => {
                e.stopPropagation();
                onCustChange('');
                setOpen(false);
                setQuery('');
            };

            return (
                <div ref={wrapRef} style={{ position: 'relative', display: 'flex', alignItems: 'center', gap: 8 }}>

                    {/* ── Trigger / Search box ─────────────────────────────────── */}
                    <div
                        onClick={!open ? openDropdown : undefined}
                        className={`flex items-center gap-2 bg-slate-800 border rounded-lg transition-all cursor-pointer ${
                            open ? 'border-indigo-500 ring-1 ring-indigo-500/30' : 'border-slate-700 hover:border-slate-500'
                        }`}
                        style={{ minWidth: 220, maxWidth: 300, height: 34, padding: '0 10px' }}
                    >
                        {/* Status dot — only when a customer is selected and dropdown closed */}
                        {/* cust && !open && (
                            <span
                                className="shrink-0 w-2 h-2 rounded-full"
                                style={{ background: STATUS_META(cust.onboarding_status).dot }}
                            />
                        ) */}

                        {open ? (
                            <input
                                ref={inputRef}
                                value={query}
                                onChange={e => setQuery(e.target.value)}
                                onKeyDown={e => {
                                    if (e.key === 'Escape') {
                                        setOpen(false);
                                        setQuery('');
                                    }
                                    if (e.key === 'Enter' && filtered.length === 1) select(String(filtered[0].id));
                                }}
                                placeholder="Type to search…"
                                className="flex-1 bg-transparent text-sm text-slate-100 placeholder:text-slate-600 outline-none min-w-0"
                                style={{ fontFamily: 'Inter, sans-serif' }}
                                    onClick={e => e.stopPropagation()}
                            />
                        ) : (
                            <span
                                className={`flex-1 text-sm truncate leading-none ${cust ? 'text-slate-100' : 'text-slate-500'}`}
                                style={{ fontFamily: 'Inter, sans-serif' }}>
                        {cust ? cust.company_name : '— Select Customer —'}
                    </span>
                        )}

                        <span className="flex items-center gap-1 shrink-0 ml-1">
                    {cust && !open && (
                        <button
                            onClick={clear}
                            className="text-slate-600 hover:text-slate-300 transition-colors leading-none"
                            title="Clear selection"
                        >
                            <svg className="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2.5}
                                      d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    )}
                            <svg
                                className={`w-3.5 h-3.5 text-slate-500 transition-transform ${open ? 'rotate-180' : ''}`}
                                fill="none" viewBox="0 0 24 24" stroke="currentColor"
                            >
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 9l-7 7-7-7"/>
                    </svg>
                </span>
                    </div>

                    {/* ── Info button (only when customer selected) ────────────── */}
                    {cust && !open && (
                        <button
                            onClick={() => setInfoOpen(p => !p)}
                            title="Customer details"
                            className={`w-[34px] h-[34px] flex items-center justify-center rounded-lg border transition-all text-xs font-bold shrink-0 ${
                                infoOpen
                                    ? 'bg-indigo-600 border-indigo-500 text-white'
                                    : 'bg-slate-800 border-slate-700 text-slate-400 hover:border-indigo-500/60 hover:text-indigo-400'
                            }`}
                        >ℹ</button>
                    )}

                    {/* ── Dropdown list ────────────────────────────────────────── */}
                    {open && (
                        <div
                            className="absolute right-0 z-50 bg-slate-800 border border-slate-600 rounded-xl shadow-2xl overflow-hidden"
                            style={{ top: 'calc(100% + 6px)', minWidth: 280, maxWidth: 360 }}
                        >
                            {filtered.length === 0 ? (
                                <div className="px-4 py-3 text-sm text-slate-500 italic">No customers match</div>
                            ) : (
                                <div style={{ maxHeight: 280, overflowY: 'auto' }}>
                                    {filtered.map(c => {
                                        const sm = STATUS_META(c.onboarding_status);
                                        const isSel = String(c.id) === String(custId);
                                        return (
                                            <button
                                                key={c.id}
                                                onClick={() => select(String(c.id))}
                                                className={`w-full flex items-center gap-3 px-4 py-2.5 text-left transition-colors ${
                                                    isSel
                                                        ? 'bg-indigo-500/15 text-indigo-200'
                                                        : 'text-slate-200 hover:bg-slate-700/60'
                                                }`}
                                            >
                                                {/* Status dot */}
                                                {/* <span
                                                    className="shrink-0 w-1.5 h-1.5 rounded-full mt-px"
                                                    style={{ background: sm.dot }}
                                                    /> */}

                                                {/* Company name */}
                                                <span className="flex-1 text-sm truncate leading-tight"
                                                      style={{ fontFamily: 'Inter, sans-serif' }}>
                                            {c.company_name}
                                        </span>

                                                {/* Status pill */}
                                                <span
                                                    className={`shrink-0 mono text-[10px] font-semibold px-1.5 py-0.5 rounded border ${sm.pill}`}>
                                            {sm.label || 'unknown'}
                                        </span>

                                                {isSel && (
                                                    <svg className="w-3.5 h-3.5 text-indigo-400 shrink-0" fill="none"
                                                         viewBox="0 0 24 24" stroke="currentColor">
                                                        <path strokeLinecap="round" strokeLinejoin="round"
                                                              strokeWidth={2.5} d="M5 13l4 4L19 7"/>
                                                    </svg>
                                                )}
                                            </button>
                                        );
                                    })}
                                </div>
                            )}
                        </div>
                    )}

                    {/* ── Customer info popover ────────────────────────────────── */}
                    {infoOpen && cust && (
                        <div
                            className="absolute right-0 z-50 w-72 bg-slate-800 border border-slate-600 rounded-xl shadow-2xl overflow-hidden"
                            style={{ top: 'calc(100% + 6px)' }}
                        >
                            <div
                                className="px-4 py-3 border-b border-slate-700 bg-slate-900/60 flex items-center gap-2.5">
                        <span
                            className="w-2 h-2 rounded-full shrink-0"
                            style={{ background: STATUS_META(cust.onboarding_status).dot }}
                        />
                                <div className="min-w-0">
                                    <p className="text-sm font-semibold text-slate-100 truncate leading-tight">{cust.company_name}</p>
                                    <p className="mono text-[10px] text-slate-500 truncate">{cust.primary_contact_email || cust.email}</p>
                                </div>
                            </div>
                            <div className="divide-y divide-slate-700/50">
                                {[
                                    ['Sepio ID', cust.sepio_company_id ||
                                    <span className="text-red-400">not registered</span>],
                                    ['Status', cust.onboarding_status || '—'],
                                    ['IEC', cust.iec_number || '—'],
                                    ['GST', cust.gst_number || '—'],
                                    ['Contact', cust.primary_contact_email || cust.email || '—'],
                                ].map(([k, v]) => (
                                    <div key={k} className="flex items-center justify-between px-4 py-2">
                                        <span className="text-xs text-slate-500 font-medium w-16 shrink-0">{k}</span>
                                        <span
                                            className="mono text-xs text-slate-200 text-right truncate max-w-[170px]">{v}</span>
                                    </div>
                                ))}
                            </div>
                        </div>
                    )}
                </div>
            );
        }

        /* ══════════════════════════════════════════════════════════════════════════
           SIDEBAR
        ══════════════════════════════════════════════════════════════════════════ */

        function Sidebar({selId, onSelect, user, onLogout}) {
            const [open, setOpen] = useState(() => Object.fromEntries(GROUPS.map(g => [g.id, false])));

            return (
                <aside
                    style={{ width: 256, flexShrink: 0, display: 'flex', flexDirection: 'column', overflow: 'hidden' }}
                        className="border-r border-slate-700 bg-slate-900">

                    {/* Endpoint list */}
                    <nav className="flex-1 overflow-y-auto py-2">
                        {GROUPS.map(g => (
                            <div key={g.id} className="mb-0.5">
                                <button
                                    onClick={() => setOpen(p => ({...p, [g.id]: !p[g.id]}))}
                                    className="w-full flex items-center justify-between px-4 py-2 text-slate-400 hover:text-slate-100 hover:bg-slate-800/60 transition-colors"
                                >
                            <span className="flex items-center gap-2 text-xs font-semibold tracking-wide uppercase">
                                <span className="inline-block w-2 h-2 rounded-full" style={{ background: g.dot }}/>
                                {g.label}
                            </span>
                                    <svg className={`w-3 h-3 transition-transform ${open[g.id] ? '' : '-rotate-90'}`}
                                         fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2}
                                              d="M19 9l-7 7-7-7"/>
                                    </svg>
                                </button>

                                {open[g.id] && g.eps.map(ep => (
                                    <button
                                        key={ep.id} onClick={() => onSelect(ep)} title={ep.path}
                                        className={`w-full text-left flex items-center gap-2 pl-5 pr-3 py-2 text-xs transition-all border-l-2 ${
                                            selId === ep.id
                                                ? 'text-indigo-300 bg-indigo-500/15 border-indigo-400'
                                                : 'text-slate-400 hover:text-slate-100 hover:bg-slate-800/50 border-transparent hover:border-slate-600'
                                        }`}
                                    >
                                <span
                                    className={`shrink-0 mono text-[10px] font-bold px-1.5 py-0.5 rounded ${METHOD_CLS[ep.method]}`}>
                                    {ep.method}
                                </span>
                                        <span className="truncate leading-tight">{ep.label}</span>
                                        {ep.file && <span className="ml-auto opacity-40 text-xs">📎</span>}
                                    </button>
                                ))}
                            </div>
                        ))}
                    </nav>

                    {/* User footer */}
                    <div className="border-t border-slate-700 px-3 py-3 bg-slate-900/80 flex items-center gap-2.5">
                        <div
                            className="w-8 h-8 rounded-full bg-gradient-to-br from-indigo-500 to-purple-600 flex items-center justify-center text-xs font-bold text-white shrink-0 select-none">
                            {initials(user?.name)}
                        </div>
                        <div className="flex-1 min-w-0">
                            <p className="text-xs font-semibold text-slate-200 truncate leading-tight">{user?.name}</p>
                            <p className="mono text-[10px] text-slate-500 truncate leading-tight">{user?.email}</p>
                        </div>
                        <button
                            onClick={onLogout}
                            title="Sign out"
                            className="shrink-0 w-7 h-7 flex items-center justify-center rounded-lg text-slate-500 hover:text-red-400 hover:bg-red-500/10 border border-transparent hover:border-red-500/30 transition-all"
                        >
                            <svg className="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2}
                                      d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                            </svg>
                        </button>
                    </div>
                </aside>
            );
        }

        /* ══════════════════════════════════════════════════════════════════════════
           REQUEST BAR  (method + URL + send button)
        ══════════════════════════════════════════════════════════════════════════ */

        function RequestBar({ep, cust, loading, onSend}) {
            const canSend = ep && !loading && !(ep.needsCust && !cust) && !(ep.file);
            // Note: file check is loose here — actual block is in the payload panel
            // We allow send unless definitively blocked
            const blocked = ep && ((ep.needsCust && !cust));

            return (
                <div className="flex items-center gap-3 px-4 py-3 border-b border-slate-700 bg-slate-900/90 shrink-0">
                    {ep ? (<>
                <span className={`shrink-0 mono text-xs font-bold px-2.5 py-1 rounded ${METHOD_CLS[ep.method]}`}>
                    {ep.method}
                </span>
                        <div
                            className="flex-1 min-w-0 mono text-sm rounded-lg bg-slate-950/60 border border-slate-700 px-3 py-2 truncate text-slate-300">
                            {ep.internal
                                ? <span className="text-amber-400 font-medium">internal · refresh-token</span>
                                : <><span className="text-slate-600">{BASE}</span><span>{ep.path}</span></>
                            }
                        </div>
                        {ep.auth && (
                            <span
                                className="shrink-0 mono text-xs text-amber-400 bg-amber-500/20 border border-amber-500/30 px-2.5 py-1 rounded font-bold tracking-wide">JWT</span>
                        )}
                    </>) : (
                        <div className="flex-1 mono text-sm text-slate-600 italic">Select an endpoint from the
                            sidebar</div>
                    )}

                    <button
                        onClick={onSend}
                        disabled={!ep || loading || blocked}
                        className={`shrink-0 flex items-center gap-2 px-5 py-2 rounded-lg text-sm font-semibold transition-all ${
                            ep && !loading && !blocked
                                ? 'bg-indigo-600 hover:bg-indigo-500 text-white shadow-lg shadow-indigo-900/30'
                                : 'bg-slate-700/60 text-slate-600 cursor-not-allowed'
                        }`}
                    >
                        {loading
                            ? <>
                                <svg className="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                    <circle cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4"
                                            className="opacity-20"/>
                                    <path fill="currentColor" d="M4 12a8 8 0 018-8V0C5.4 0 0 5.4 0 12h4z"/>
                                </svg>
                                Sending…</>
                            : 'Send →'
                        }
                    </button>
                </div>
            );
        }

        /* ── Info bar (notes / warnings) ──────────────────────────────────────── */
        function InfoBar({ep, cust, file}) {
            if (!ep) return null;
            const warnCust = ep.needsCust && !cust;
            const warnFile = ep.file && !file;

            return (
                <div className="px-4 py-2.5 border-b border-slate-700 bg-slate-900/50 shrink-0 space-y-1.5">
                    <p className="text-sm text-slate-400 leading-relaxed">{ep.note}</p>
                    {warnCust && (
                        <p className="text-xs text-amber-400 flex items-center gap-1.5">
                            <svg className="w-3.5 h-3.5 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                <path fillRule="evenodd"
                                      d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z"
                                      clipRule="evenodd"/>
                            </svg>
                            Select a customer — JWT authentication required
                        </p>
                    )}
                    {warnFile && (
                        <p className="text-xs text-amber-400 flex items-center gap-1.5">
                            <svg className="w-3.5 h-3.5 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                <path fillRule="evenodd"
                                      d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z"
                                      clipRule="evenodd"/>
                            </svg>
                            A file attachment is required
                        </p>
                    )}
                </div>
            );
        }

        /* ══════════════════════════════════════════════════════════════════════════
           REQUEST PAYLOAD PANEL
        ══════════════════════════════════════════════════════════════════════════ */

        function RequestPayload({ep, payload, setPayload, file, setFile}) {
            if (!ep) {
                return (
                    <div className="flex-1 flex items-center justify-center bg-slate-950/20 select-none">
                        <div className="text-center space-y-3">
                            <div className="text-6xl opacity-5">⚡</div>
                            <p className="mono text-xs text-slate-600 tracking-widest font-medium uppercase">Request
                                Payload</p>
                        </div>
                    </div>
                );
            }

            if (ep.internal) {
                return (
                    <div className="flex-1 flex items-center justify-center px-6 bg-slate-950/20">
                        <p className="text-sm text-slate-500 text-center leading-relaxed">
                            Internal action — no request body needed.<br/>
                            <span className="text-slate-600">Select a customer and press Send.</span>
                        </p>
                    </div>
                );
            }

            return (
                <div style={{ display: 'flex', flexDirection: 'column', flex: 1, overflow: 'hidden' }}>
                    {/* File picker */}
                    {ep.file && (
                        <div className="px-4 py-3 border-b border-slate-700 bg-slate-950/30 shrink-0">
                            <p className="mono text-[10px] tracking-widest uppercase text-slate-500 font-semibold mb-2">File
                                Attachment</p>
                            <label className="flex items-center gap-3 cursor-pointer">
                        <span
                            className="flex items-center gap-2 px-3.5 py-1.5 rounded-lg bg-slate-800 hover:bg-slate-700 border border-slate-600 hover:border-indigo-500/60 text-xs text-slate-200 transition-all font-medium">
                            <svg className="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path
                                strokeLinecap="round" strokeLinejoin="round" strokeWidth={2}
                                d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                            Choose File
                        </span>
                                <input type="file" accept=".pdf,.jpg,.jpeg,.png" className="sr-only"
                                       onChange={e => setFile(e.target.files[0] || null)}/>
                                {file
                                    ? <span
                                        className="text-xs text-emerald-400 mono">✓ {file.name} ({(file.size / 1024).toFixed(1)} KB)</span>
                                    : <span className="text-xs text-slate-600">PDF, JPG, PNG</span>
                                }
                            </label>
                        </div>
                    )}

                    {/* Payload editor */}
                    <div
                        style={{ flex: 1, display: 'flex', flexDirection: 'column', padding: '12px 16px', gap: 8, minHeight: 0 }}>
                        <div className="flex items-center justify-between shrink-0">
                    <span className="mono text-[10px] tracking-widest uppercase text-slate-500 font-semibold">
                        {ep.method === 'GET' ? 'Query Params' : 'Request Body'} · JSON
                    </span>
                            <button
                                onClick={() => setPayload(fmtJson(payload))}
                                className="mono text-xs text-slate-500 hover:text-slate-300 px-2.5 py-1 rounded hover:bg-slate-800 transition-colors"
                            >Format
                            </button>
                        </div>
                        <textarea
                            value={payload}
                            onChange={e => setPayload(e.target.value)}
                            spellCheck={false}
                            className="flex-1 w-full bg-slate-950/60 border border-slate-700 rounded-lg px-4 py-3 mono text-sm text-slate-200 focus:border-indigo-500 transition-colors leading-relaxed placeholder:text-slate-700"
                            placeholder="{}"
                        />
                    </div>
                </div>
            );
        }

        /* ══════════════════════════════════════════════════════════════════════════
           RESPONSE PANEL
        ══════════════════════════════════════════════════════════════════════════ */

        function ResponsePanel({resp}) {
            const [copied, setCopied] = useState(false);

            const copy = () => {
                try {
                    navigator.clipboard.writeText(JSON.stringify(resp?.body, null, 2));
                    setCopied(true);
                    setTimeout(() => setCopied(false), 1500);
                } catch {
                }
            };

            if (!resp) {
                return (
                    <div className="flex-1 flex items-center justify-center bg-slate-950/10 select-none">
                        <p className="mono text-xs text-slate-700 tracking-widest font-medium uppercase">Response</p>
                    </div>
                );
            }

            const bodyStr = JSON.stringify(resp.body, null, 2);

            return (
                <div style={{ display: 'flex', flexDirection: 'column', flex: 1, overflow: 'hidden' }}>
                    {/* Meta bar */}
                    <div
                        className="flex items-center gap-3 px-4 py-2.5 border-b border-slate-700 bg-slate-900/60 shrink-0">
                <span className={`mono text-xs font-bold px-2.5 py-0.5 rounded ${statusCls(resp.status)}`}>
                    {resp.status || 'ERR'}
                </span>
                        {resp.elapsed_ms !== undefined && (
                            <span className="mono text-xs text-slate-500">{resp.elapsed_ms} ms</span>
                        )}
                        <span className="mono text-xs text-slate-700">{(bodyStr.length / 1024).toFixed(2)} KB</span>
                        <div className="flex-1"/>
                        <button
                            onClick={copy}
                            className={`mono text-xs px-2.5 py-1 rounded transition-all ${copied ? 'text-emerald-400 bg-emerald-500/10' : 'text-slate-500 hover:text-slate-300 hover:bg-slate-800'}`}
                        >
                            {copied ? '✓ Copied' : 'Copy JSON'}
                        </button>
                    </div>
                    <div className="flex-1 overflow-auto p-4">
                <pre className="mono text-sm leading-relaxed whitespace-pre-wrap break-words"
                     dangerouslySetInnerHTML={{ __html: colorJson(bodyStr) }}/>
                    </div>
                </div>
            );
        }

        /* ── Resizer ──────────────────────────────────────────────────────────── */
        function Resizer({leftWidth, setLeftWidth}) {
            const dragging = useRef(false);

            const onMouseDown = () => {
                dragging.current = true;
                document.body.style.cursor = 'ew-resize';
                document.body.style.userSelect = 'none';
            };

            useEffect(() => {
                const onMove = (e) => {
                    if (!dragging.current) return;
                    // 256 = sidebar width
                    setLeftWidth(Math.max(280, Math.min(window.innerWidth - 256 - 280, e.clientX - 256)));
                };
                const onUp = () => {
                    dragging.current = false;
                    document.body.style.cursor = '';
                    document.body.style.userSelect = '';
                };
                document.addEventListener('mousemove', onMove);
                document.addEventListener('mouseup', onUp);
                return () => {
                    document.removeEventListener('mousemove', onMove);
                    document.removeEventListener('mouseup', onUp);
                };
            }, [setLeftWidth]);

            return (
                <div
                    onMouseDown={onMouseDown}
                    className="ew-resize hover:bg-indigo-500/60 transition-colors shrink-0"
                    style={{ width: 4, background: '#1e293b', position: 'relative', flexShrink: 0 }}
                >
                    <div style={{ position: 'absolute', top: 0, bottom: 0, left: -4, right: -4 }}/>
                </div>
            );
        }

        /* ══════════════════════════════════════════════════════════════════════════
           INSPECTOR  (main layout, shown when authenticated + authorised)
        ══════════════════════════════════════════════════════════════════════════ */

        function Inspector({user, onLogout}) {
            const [customers, setCustomers] = useState([]);
            const [custId, setCustId] = useState('');
            const [ep, setEp] = useState(null);
            const [loading, setLoading] = useState(false);
            const [leftWidth, setLeftWidth] = useState(520);
            const [epStates, setEpStates] = useState({});

            // Fetch customers
            useEffect(() => {
                apiFetch(`${API}/sepio-inspector/customers`)
                    .then(r => r.ok ? r.json() : [])
                    .then(setCustomers)
                    .catch(() => {
                    });
            }, []);

            const cust = customers.find(c => String(c.id) === String(custId)) || null;

            const changeCust = (id) => {
                setCustId(id);
                const c = customers.find(x => String(x.id) === String(id)) || null;
                if (ep?.id) {
                    setEpStates(prev => ({
                        ...prev,
                        [ep.id]: {...prev[ep.id], payload: JSON.stringify(ep.payload ? ep.payload(c) : {}, null, 2)}
                    }));
                }
            };

            const selectEp = (e) => {
                setEp(e);
                if (!epStates[e.id]) {
                    setEpStates(prev => ({
                        ...prev,
                        [e.id]: {
                            payload: JSON.stringify(e.payload ? e.payload(cust) : {}, null, 2),
                            file: null,
                            resp: null
                        }
                    }));
                }
            };

            const cur = ep ? (epStates[ep.id] || {payload: '{}', file: null, resp: null}) : {};

            const updEp = (key, val) => {
                if (!ep) return;
                setEpStates(prev => ({...prev, [ep.id]: {...prev[ep.id], [key]: val}}));
            };

            const send = async () => {
                if (!ep) return;
                setLoading(true);
                const t0 = Date.now();
                try {
                    let data;

                    if (ep.internal) {
                        const r = await apiFetch(`${API}/sepio-inspector/refresh-token`, {
                            method: 'POST',
                            body: JSON.stringify({customer_id: custId}),
                        });
                        data = await r.json();

                    } else if (ep.file) {
                        const fd = new FormData();
                        fd.append('endpoint', ep.path);
                        fd.append('customer_id', custId);
                        fd.append('payload', cur.payload || '{}');
                        if (cur.file) fd.append('file', cur.file);
                        const r = await apiFetch(`${API}/sepio-inspector/proxy-file`, {method: 'POST', body: fd});
                        data = await r.json();

                    } else {
                        let parsed = {};
                        try {
                            parsed = JSON.parse(cur.payload || '{}');
                        } catch {
                        }
                        const r = await apiFetch(`${API}/sepio-inspector/proxy`, {
                            method: 'POST',
                            body: JSON.stringify({
                                endpoint: ep.path,
                                method: ep.method,
                                customer_id: custId || null,
                                authenticated: ep.auth,
                                payload: parsed
                            }),
                        });
                        data = await r.json();
                    }

                    if (!data.elapsed_ms) data.elapsed_ms = Date.now() - t0;
                    updEp('resp', data);
                } catch (e) {
                    updEp('resp', {status: 0, body: {error: e.message}, elapsed_ms: Date.now() - t0});
                } finally {
                    setLoading(false);
                }
            };

            return (
                <div style={{ display: 'flex', flexDirection: 'column', height: '100vh' }}>

                    {/* ── HEADER ─────────────────────────────────────────────────── */}
                    <header style={{ height: 54, flexShrink: 0 }}
                        className="flex items-center justify-between px-5 border-b border-slate-700 bg-slate-900">
                        <div className="flex items-center gap-3">
                            <div
                                className="w-8 h-8 rounded-lg bg-gradient-to-br from-indigo-500 to-purple-600 flex items-center justify-center shadow-lg">
                                <svg className="w-4 h-4 text-white" fill="none" viewBox="0 0 24 24"
                                     stroke="currentColor">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2.5}
                                          d="M13 10V3L4 14h7v7l9-11h-7z"/>
                                </svg>
                            </div>
                            <span className="text-sm font-bold text-slate-100 tracking-tight">Sepio Inspector</span>
                            <span
                                className="mono text-xs text-slate-700 bg-slate-800 px-2 py-0.5 rounded-md border border-slate-700">dev</span>
                        </div>

                        <CustomerSelector
                            customers={customers}
                            custId={custId}
                            onCustChange={changeCust}
                        />
                    </header>

                    {/* ── BODY ───────────────────────────────────────────────────── */}
                    <div style={{ flex: 1, display: 'flex', overflow: 'hidden' }}>

                        {/* Sidebar */}
                        <Sidebar selId={ep?.id} onSelect={selectEp} user={user} onLogout={onLogout}/>

                        {/* Main column */}
                        <div style={{ flex: 1, display: 'flex', flexDirection: 'column', overflow: 'hidden' }}>

                            {/* Request bar */}
                            <RequestBar ep={ep} cust={cust} loading={loading} onSend={send}/>

                            {/* Info bar */}
                            <InfoBar ep={ep} cust={cust} file={cur.file}/>

                            {/* Split panel */}
                            <div style={{ flex: 1, display: 'flex', overflow: 'hidden' }}>

                                {/* Left: payload */}
                                <div
                                    style={{ width: leftWidth, display: 'flex', flexDirection: 'column', overflow: 'hidden' }}
                                        className="border-r border-slate-700/50">
                                    <RequestPayload
                                        ep={ep}
                                        payload={cur.payload}
                                        setPayload={v => updEp('payload', v)}
                                        file={cur.file}
                                        setFile={v => updEp('file', v)}
                                    />
                                </div>

                                {/* Resizer */}
                                <Resizer leftWidth={leftWidth} setLeftWidth={setLeftWidth}/>

                                {/* Right: response */}
                                <div style={{ flex: 1, display: 'flex', flexDirection: 'column', overflow: 'hidden' }}>
                                    <ResponsePanel resp={cur.resp}/>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            );
        }

        /* ══════════════════════════════════════════════════════════════════════════
           APP  (auth wrapper)
        ══════════════════════════════════════════════════════════════════════════ */

        function App() {
            const [token, setTokenState] = useState(getToken());
            const [user, setUser] = useState(null);
            const [status, setStatus] = useState('loading'); // loading | ok | no_perm | unauthed

            // Global 401 handler
            useEffect(() => {
                const h = () => {
                    saveToken(null);
                    setTokenState(null);
                    setUser(null);
                    setStatus('unauthed');
                };
                window.addEventListener('sepio:401', h);
                return () => window.removeEventListener('sepio:401', h);
            }, []);

            // Restore session from stored token
            useEffect(() => {
                if (!token) {
                    setStatus('unauthed');
                    return;
                }
                setStatus('loading');
                apiFetch(`${API}/sepio-inspector/me`)
                    .then(r => {
                        if (r.status === 401) {
                            saveToken(null);
                            setTokenState(null);
                            setStatus('unauthed');
                            return null;
                        }
                        if (r.status === 403) {
                            setStatus('no_perm');
                            return null;
                        }
                        return r.json();
                    })
                    .then(data => {
                        if (data) {
                            setUser(data);
                            setStatus('ok');
                        }
                    })
                    .catch(() => setStatus('unauthed'));
            }, [token]);

            const handleLogin = (tok, u) => {
                saveToken(tok);
                setTokenState(tok);
                setUser(u);
                setStatus('ok');
            };

            const handleLogout = async () => {
                try {
                    await apiFetch(`${API}/auth/logout`, {method: 'POST'});
                } catch {
                }
                saveToken(null);
                setTokenState(null);
                setUser(null);
                setStatus('unauthed');
            };

            if (status === 'loading') return <LoadingScreen/>;
            if (status === 'unauthed') return <LoginScreen onLogin={handleLogin}/>;
            if (status === 'no_perm') return <PermissionDenied onLogout={handleLogout}/>;
            return <Inspector user={user} onLogout={handleLogout}/>;
        }

        ReactDOM.createRoot(document.getElementById('root')).render(<App/>);
    </script>
@endverbatim
</body>
</html>
