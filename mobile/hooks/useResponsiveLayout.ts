import { useMemo } from 'react';
import { useWindowDimensions } from 'react-native';

export function useResponsiveLayout() {
  const { width, height } = useWindowDimensions();
  return useMemo(() => {
    const shortest = Math.min(width, height);
    const isTabletDevice = shortest >= 600;
    return {
      width,
      height,
      isTabletDevice,
      navRailWidth: width >= 1000 ? 240 : 200,
      horizontalPadding: isTabletDevice ? 28 : 16,
    };
  }, [width, height]);
}
