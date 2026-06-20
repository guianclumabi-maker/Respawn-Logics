<?php

function renderLogo($variant = 'centered') {
    $html = '';
    if ($variant === 'centered') {
        $html .= '
        <div class="logo text-center" style="display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 12px; margin-bottom: 24px;">
            <div style="width: 48px; height: 48px; background: linear-gradient(135deg, #00e07a, #00b8ff); border-radius: 12px; display: flex; align-items: center; justify-content: center; box-shadow: 0 0 20px rgba(0,224,122,0.4);">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#000" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="6" x2="10" y1="12" y2="12"/><line x1="8" x2="8" y1="10" y2="14"/><line x1="15" x2="15.01" y1="13" y2="13"/><line x1="18" x2="18.01" y1="11" y2="11"/><rect width="20" height="12" x="2" y="6" rx="2"/></svg>
            </div>
            <div style="display: flex; align-items: center; gap: 8px;">
                <span style="font-family: \'JetBrains Mono\', monospace; font-size: 24px; font-weight: 700; color: var(--text-primary); letter-spacing: -0.5px; margin: 0;">Respawn Logics</span>
                <span style="font-family: \'JetBrains Mono\', monospace; font-size: 10px; font-weight: 700; letter-spacing: 0.1em; color: #00e07a; background: rgba(0,224,122,0.1); padding: 4px 8px; border: 1px solid rgba(0,224,122,0.22); border-radius: 4px;">v2.0</span>
            </div>
        </div>
        ';
    } elseif ($variant === 'sidebar') {
        $html .= '
        <div style="width: 32px; height: 32px; background: linear-gradient(135deg, #00e07a, #00b8ff); border-radius: 7px; display: flex; align-items: center; justify-content: center; color: #000; font-size: 16px; margin-right: 12px;">
            <svg xmlns="http://www.w3.org/2000/svg" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="#000" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="6" x2="10" y1="12" y2="12"/><line x1="8" x2="8" y1="10" y2="14"/><line x1="15" x2="15.01" y1="13" y2="13"/><line x1="18" x2="18.01" y1="11" y2="11"/><rect width="20" height="12" x="2" y="6" rx="2"/></svg>
        </div>
        <div style="display: flex; align-items: center; gap: 8px;">
            <span class="brand-text" style="font-family: \'JetBrains Mono\', monospace; font-weight: 700; color: var(--text-primary); text-transform: none; font-size: 15px; letter-spacing: -0.5px;">Respawn Logics</span>
            <span style="font-family: \'JetBrains Mono\', monospace; font-size: 9px; font-weight: 700; letter-spacing: 0.1em; color: #00e07a; background: rgba(0,224,122,0.1); padding: 2px 4px; border: 1px solid rgba(0,224,122,0.22); border-radius: 4px;">v2.0</span>
        </div>
        ';
    } elseif ($variant === 'navbar') {
        $html .= '
        <a href="#" class="nav-logo">
            <div class="logo-mark"><svg xmlns="http://www.w3.org/2000/svg" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="6" x2="10" y1="12" y2="12"/><line x1="8" x2="8" y1="10" y2="14"/><line x1="15" x2="15.01" y1="13" y2="13"/><line x1="18" x2="18.01" y1="11" y2="11"/><rect width="20" height="12" x="2" y="6" rx="2"/></svg></div>
            Respawn Logics
            <span class="version-pill">v2.0</span>
        </a>
        ';
    }
    
    return $html;
}
