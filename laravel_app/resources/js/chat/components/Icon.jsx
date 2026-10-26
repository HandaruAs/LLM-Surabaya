import { createContext, useContext } from 'react';

// Path SVG berasal dari App\Support\Icons (dikirim server lewat data-config), bukan dari input user.
export const IconsContext = createContext({});

export default function Icon({ name, className = 'size-4' }) {
  const icons = useContext(IconsContext);
  return (
    <svg
      className={className + ' shrink-0'}
      viewBox="0 0 24 24"
      fill="none"
      stroke="currentColor"
      strokeWidth="2"
      strokeLinecap="round"
      strokeLinejoin="round"
      aria-hidden="true"
      dangerouslySetInnerHTML={{ __html: icons[name] || '' }}
    />
  );
}
