import React, { useEffect, useRef, useState } from 'react';

export function SpinningDonut() {
  const [frame, setFrame] = useState('');
  const requestRef = useRef<number>();

  useEffect(() => {
    let A = 0;
    let B = 0;
    
    const renderFrame = () => {
      const b = [];
      const z = [];
      A += 0.07;
      B += 0.03;
      
      const cA = Math.cos(A), sA = Math.sin(A),
            cB = Math.cos(B), sB = Math.sin(B);
            
      for (let k = 0; k < 1760; k++) {
        b[k] = k % 80 === 79 ? '\n' : ' ';
        z[k] = 0;
      }
      
      for (let j = 0; j < 6.28; j += 0.07) {
        const ct = Math.cos(j), st = Math.sin(j);
        for (let i = 0; i < 6.28; i += 0.02) {
          const sp = Math.sin(i), cp = Math.cos(i),
                h = ct + 2, 
                D = 1 / (sp * h * sA + st * cA + 5), 
                t = sp * h * cA - st * sA;
          
          const x = Math.floor(40 + 30 * D * (cp * h * cB - t * sB)),
                y = Math.floor(12 + 15 * D * (cp * h * sB + t * cB)),
                o = x + 80 * y,
                N = Math.floor(8 * ((st * sA - sp * ct * cA) * cB - sp * ct * sA - st * cA - cp * ct * sB));
                
          if (y < 22 && y >= 0 && x >= 0 && x < 79 && D > z[o]) {
            z[o] = D;
            b[o] = ".,-~:;=!*#$@"[N > 0 ? N : 0];
          }
        }
      }
      setFrame(b.join(''));
      requestRef.current = requestAnimationFrame(renderFrame);
    };

    requestRef.current = requestAnimationFrame(renderFrame);
    return () => {
      if (requestRef.current) cancelAnimationFrame(requestRef.current);
    };
  }, []);

  return (
    <div className="flex justify-center items-center py-8">
      <pre className="font-mono text-[10px] sm:text-xs text-orange-500 leading-none tracking-tighter whitespace-pre drop-shadow-[0_0_8px_rgba(249,115,22,0.6)] font-bold">
        {frame}
      </pre>
    </div>
  );
}
