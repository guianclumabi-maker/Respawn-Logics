import { describe, it, expect, vi } from 'vitest';
import { render, act } from '@testing-library/react';
import { ATSDashboard } from '../../src/app/components/ATSDashboard';
import { MemoryRouter } from 'react-router-dom';

// Mock dependencies that ATSDashboard might use like APIs or contexts
vi.mock('../../src/app/context/AppContext', () => ({
  useApp: () => ({ theme: 'light' })
}));

global.fetch = vi.fn(() =>
  Promise.resolve({
    ok: true,
    json: () => Promise.resolve({ data: [] }),
  })
) as any;

// Mock Recharts to avoid ResizeObserver errors in JSDOM
vi.mock('recharts', async () => {
  const OriginalRechartsModule = await vi.importActual<any>('recharts');
  return {
    ...OriginalRechartsModule,
    ResponsiveContainer: ({ children }: any) => (
      <div style={{ width: '100%', height: 300 }}>{children}</div>
    ),
  };
});

// Polyfill for ResizeObserver which is missing in JSDOM
global.ResizeObserver = class ResizeObserver {
  observe() {}
  unobserve() {}
  disconnect() {}
};

describe('ATSDashboard', () => {
  it('renders successfully without crashing', async () => {
    let container: HTMLElement | null = null;
    await act(async () => {
      const result = render(
        <MemoryRouter>
          <ATSDashboard onViewChange={() => {}} />
        </MemoryRouter>
      );
      container = result.container;
    });
    expect(container).not.toBeNull();
    expect(container).not.toBeEmptyDOMElement();
  });
});
