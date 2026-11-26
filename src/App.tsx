/**
 * @license
 * SPDX-License-Identifier: Apache-2.0
 */

const PLUGIN_FILES = [
  {path: 'rsw-utm-grabber.php', role: 'File principale del plugin WordPress (v2.0.0)'},
  {path: 'assets/rsw-utm-grabber.js', role: 'Fallback JavaScript per cache full-page, form dinamici e pre-submit'},
  {path: 'uninstall.php', role: 'Pulizia opzioni in disinstallazione (single site e Multisite)'},
  {path: 'readme.txt', role: 'Documentazione WordPress e changelog'},
  {path: '.github/workflows/deploy.yml', role: 'Workflow GitHub Actions per deploy su WordPress.org (rswstudio-utmgrabber)'},
  {path: '.svnignore', role: 'Esclusioni per la WordPress Plugin Directory'},
];

const SUPPORTED_PARAMS = [
  'utm_campaign',
  'utm_source',
  'utm_medium',
  'utm_term',
  'utm_content',
  'gclid',
  'fbclid',
];

export default function App() {
  return (
    <main className="min-h-screen bg-slate-50 text-slate-900 px-6 py-12">
      <div className="max-w-3xl mx-auto space-y-8">
        <header className="space-y-2 border-b border-slate-200 pb-6">
          <p className="text-xs font-semibold uppercase tracking-wider text-slate-500">
            Plugin WordPress · rswstudio · slug: rswstudio-utmgrabber
          </p>
          <h1 className="text-3xl font-bold tracking-tight">RSW UTM Grabber v2.0.0</h1>
          <p className="text-slate-600">
            Compatibile con PHP 8.3 · WordPress minimo 7.0 · Testato fino a WordPress 7.2
          </p>
        </header>

        <section className="bg-white border border-slate-200 rounded-lg p-6 space-y-4">
          <h2 className="text-lg font-semibold">Struttura del plugin</h2>
          <ul className="divide-y divide-slate-100 text-sm">
            {PLUGIN_FILES.map((file) => (
              <li key={file.path} className="py-2.5 flex flex-col sm:flex-row sm:justify-between gap-1">
                <code className="font-mono font-medium text-slate-900">{file.path}</code>
                <span className="text-slate-600">{file.role}</span>
              </li>
            ))}
          </ul>
        </section>

        <section className="bg-white border border-slate-200 rounded-lg p-6 space-y-3">
          <h2 className="text-lg font-semibold">Parametri e mail-tag CF7 supportati</h2>
          <div className="flex flex-wrap gap-2">
            {SUPPORTED_PARAMS.map((param) => (
              <code
                key={param}
                className="px-2.5 py-1 bg-slate-100 border border-slate-200 rounded text-xs font-mono text-slate-800"
              >
                {param}
              </code>
            ))}
          </div>
        </section>
      </div>
    </main>
  );
}
