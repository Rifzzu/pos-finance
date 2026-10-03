import * as React from 'react';

export function useMobile(breakpoint = 768): boolean {
  const [isMobile, setIsMobile] = React.useState(
    () => typeof window !== 'undefined' && window.innerWidth < breakpoint,
  );

  React.useEffect(() => {
    const onResize = () => setIsMobile(window.innerWidth < breakpoint);
    window.addEventListener('resize', onResize);
    return () => window.removeEventListener('resize', onResize);
  }, [breakpoint]);

  return isMobile;
}
