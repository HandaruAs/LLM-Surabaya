import { useRef } from 'react';
import { motion } from 'motion/react';
import * as Dropdown from '@radix-ui/react-dropdown-menu';
import * as Tooltip from '@radix-ui/react-tooltip';
import Icon from './Icon';
import { THEME_LABEL } from '../../theme';


function IconButton({ label, icon, onClick, className = '' }) {
  return (
    <Tooltip.Root>
      <Tooltip.Trigger asChild>
        <motion.button
          type="button"
          onClick={onClick}
          aria-label={label}
          whileTap={{ scale: 0.9 }}
          className={'inline-grid size-10 cursor-pointer place-items-center rounded-xl text-mute transition hover:bg-tint hover:text-ink ' + className}
        >
          <Icon name={icon} className="size-[18px]" />
        </motion.button>
      </Tooltip.Trigger>
      <Tooltip.Portal>
        <Tooltip.Content sideOffset={6} className="z-50 rounded-lg bg-ink px-2.5 py-1.5 text-xs font-medium text-paper shadow-lift">
          {label}
          <Tooltip.Arrow className="fill-ink" />
        </Tooltip.Content>
      </Tooltip.Portal>
    </Tooltip.Root>
  );
}

// Header area percakapan: judul obrolan, tema, dan menu akun.
// Tombol obrolan baru cukup di sidebar, jadi tidak diulang di sini.
export default function Header({ cfg, title, subtitle, theme, onTheme, showExpand, onExpand, onMenu }) {
  const themeIcon = theme === 'dark' ? 'moon' : theme === 'light' ? 'sun' : 'monitor';
  const account = cfg.admin || cfg.user;

  return (
    <header className="glass sticky top-0 z-20 flex items-center gap-2 border-b border-line px-3 py-2.5 md:gap-3 md:px-6">
      <IconButton label="Buka menu" icon="menu" onClick={onMenu} className="md:hidden" />
      {showExpand ? <IconButton label="Tampilkan sidebar" icon="sidebar" onClick={onExpand} className="hidden md:inline-grid" /> : null}

      <div className="min-w-0 flex-1">
        <motion.h1
          key={title}
          initial={{ opacity: 0, y: 4 }}
          animate={{ opacity: 1, y: 0 }}
          className="truncate font-serif text-[17px] font-bold md:text-lg"
        >
          {title}
        </motion.h1>
        <p className="truncate text-xs text-mute">{subtitle}</p>
      </div>

      {cfg.admin ? (
        <span className="hidden items-center gap-1 rounded-full bg-warning-bg px-2.5 py-1 text-[11px] font-bold uppercase tracking-wider text-warning-ink lg:inline-flex">
          <Icon name="shield" className="size-3.5" />Mode uji admin
        </span>
      ) : null}

      {cfg.admin ? (
        <a href={cfg.routes.admin} className="hidden items-center gap-1.5 rounded-xl border border-line bg-card px-3 py-2 text-sm font-semibold transition hover:border-brand hover:text-brand sm:inline-flex">
          <Icon name="admin" className="size-4" />Area admin
        </a>
      ) : null}
      <IconButton label={'Tema: ' + THEME_LABEL[theme]} icon={themeIcon} onClick={onTheme} />

      {account ? (
        <AccountMenu cfg={cfg} account={account} />
      ) : (
        <div className="flex shrink-0 items-center gap-2">
          <a href={cfg.routes.login} className="hidden items-center gap-1.5 rounded-xl border border-line bg-card px-3 py-2 text-sm font-semibold transition hover:border-brand hover:text-brand sm:inline-flex">
            <Icon name="login" className="size-4" />Masuk
          </a>
          <a href={cfg.routes.register} className="rounded-xl bg-gradient-to-br from-brand to-brand-strong px-3.5 py-2 text-sm font-semibold text-paper shadow-glow transition hover:opacity-95">
            Daftar
          </a>
        </div>
      )}
    </header>
  );
}

function AccountMenu({ cfg, account }) {
  const formRef = useRef(null);
  const isAdmin = Boolean(cfg.admin);

  return (
    <>
      {/* Logout tetap lewat form POST biasa (cookie JWT / session admin dihapus server). */}
      <form ref={formRef} method="POST" action={isAdmin ? cfg.routes.adminLogout : cfg.routes.logout} className="hidden">
        <input type="hidden" name="_token" value={cfg.csrf} />
      </form>
      <Dropdown.Root>
        <Dropdown.Trigger asChild>
          <button type="button" aria-label="Menu akun" className="flex cursor-pointer items-center gap-2 rounded-2xl border border-line bg-card py-1 pr-2 pl-1 transition hover:border-brand/50 data-[state=open]:border-brand">
            <span className="grid size-8 place-items-center rounded-xl bg-gradient-to-br from-brand to-brand-strong text-xs font-bold text-paper">{account.initials}</span>
            <span className="hidden max-w-[10rem] text-left leading-tight lg:block">
              <span className="block truncate text-sm font-semibold">{account.name}</span>
              <span className="block truncate text-[11px] text-mute">{isAdmin ? 'Admin' : account.email}</span>
            </span>
            <Icon name="chevronDown" className="size-4 text-mute" />
          </button>
        </Dropdown.Trigger>
        <Dropdown.Portal>
          <Dropdown.Content
            align="end"
            sideOffset={8}
            className="z-50 w-64 origin-[var(--radix-dropdown-menu-content-transform-origin)] rounded-2xl border border-line bg-card p-1.5 text-ink shadow-lift [animation:dialog-in_.18s_cubic-bezier(.22,1,.36,1)]"
          >
            <div className="flex items-center gap-3 px-2.5 py-2.5">
              <span className="grid size-10 shrink-0 place-items-center rounded-xl bg-gradient-to-br from-brand to-brand-strong text-sm font-bold text-paper">{account.initials}</span>
              <div className="min-w-0">
                <div className="truncate text-sm font-semibold">{account.name}</div>
                <div className="truncate text-xs text-mute">{account.email}</div>
              </div>
            </div>
            <Dropdown.Separator className="my-1 h-px bg-line" />
            {isAdmin ? (
              <Dropdown.Item asChild>
                <a href={cfg.routes.admin} className="flex cursor-pointer items-center gap-2.5 rounded-xl px-2.5 py-2 text-sm outline-none data-[highlighted]:bg-tint data-[highlighted]:text-brand">
                  <Icon name="admin" className="size-4" />Kembali ke area admin
                </a>
              </Dropdown.Item>
            ) : null}
            <Dropdown.Item
              onSelect={() => formRef.current?.submit()}
              className="flex cursor-pointer items-center gap-2.5 rounded-xl px-2.5 py-2 text-sm text-danger-ink outline-none data-[highlighted]:bg-danger-bg"
            >
              <Icon name="logout" className="size-4" />Keluar
            </Dropdown.Item>
          </Dropdown.Content>
        </Dropdown.Portal>
      </Dropdown.Root>
    </>
  );
}
