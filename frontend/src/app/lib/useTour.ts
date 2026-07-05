import { useCallback, useEffect, useRef } from 'react';
import { driver } from 'driver.js';
import 'driver.js/dist/driver.css';
import { apiFetch } from './apiClient';

/**
 * useTour — a reusable first-run guided walkthrough backed by driver.js.
 *
 * Behaviour:
 *  - On mount it asks the backend whether this user has already completed `tourName`
 *    (GET route=tours&action=status). If not, it auto-runs the tour once.
 *  - When the tour finishes (or is dismissed) it POSTs route=tours&action=complete so it
 *    never auto-shows again — persisted per user in the DB, so it follows them across devices.
 *  - `startTour()` lets a "?" button replay the tour on demand, regardless of completion.
 *
 * Steps use CSS selectors, e.g.:
 *   [{ element: '#tour-ts-generate', popover: { title: 'Generate', description: '...' } }]
 * Give the target elements a stable id or data attribute in the page.
 */
export function useTour(
  tourName: string,
  steps: any[],
  opts?: { enabled?: boolean }
) {
  const enabled = opts?.enabled ?? true;
  const autoChecked = useRef(false);

  const markComplete = useCallback(async () => {
    try {
      await apiFetch('/api/index.php?route=tours&action=complete', {
        method: 'POST',
        body: JSON.stringify({ tour_name: tourName }),
      });
    } catch {
      /* non-fatal: tour just may re-show next time */
    }
  }, [tourName]);

  const run = useCallback(() => {
    if (!steps || steps.length === 0) return;
    const d = driver({
      showProgress: true,
      allowClose: true,
      nextBtnText: 'Next',
      prevBtnText: 'Back',
      doneBtnText: 'Got it',
      steps,
      onDestroyed: () => {
        markComplete();
      },
    });
    d.drive();
  }, [steps, markComplete]);

  // Manual replay — always runs, even if already completed.
  const startTour = useCallback(() => {
    run();
  }, [run]);

  // First-run auto: check completion once per mount, run if not yet completed.
  useEffect(() => {
    if (!enabled || autoChecked.current) return;
    autoChecked.current = true;
    let cancelled = false;

    (async () => {
      try {
        const res = await apiFetch(
          `/api/index.php?route=tours&action=status&tour=${encodeURIComponent(tourName)}`
        );
        if (!res.ok) return;
        const json = await res.json();
        if (!cancelled && json.success && !json.completed) {
          // Small delay so the target elements are mounted before we highlight them.
          setTimeout(() => {
            if (!cancelled) run();
          }, 500);
        }
      } catch {
        /* non-fatal */
      }
    })();

    return () => {
      cancelled = true;
    };
  }, [enabled, tourName, run]);

  return { startTour };
}
