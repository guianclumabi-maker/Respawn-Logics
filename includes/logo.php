<?php

function renderLogo($variant = 'centered') {
    $html = '';
    if ($variant === 'centered') {
        $html .= '
        <div class="logo text-center" style="display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 12px; margin-bottom: 24px;">
            <div style="width: 48px; height: 48px; background: linear-gradient(135deg, #00e07a, #00b8ff); border-radius: 12px; display: flex; align-items: center; justify-content: center; box-shadow: 0 0 20px rgba(0,224,122,0.4);">
                <i class="fa-solid fa-gamepad" style="font-size: 24px; color: #000;"></i>
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
            <i class="fa-solid fa-gamepad"></i>
        </div>
        <div style="display: flex; align-items: center; gap: 8px;">
            <span class="brand-text" style="font-family: \'JetBrains Mono\', monospace; font-weight: 700; color: var(--text-primary); text-transform: none; font-size: 15px; letter-spacing: -0.5px;">Respawn Logics</span>
            <span style="font-family: \'JetBrains Mono\', monospace; font-size: 9px; font-weight: 700; letter-spacing: 0.1em; color: #00e07a; background: rgba(0,224,122,0.1); padding: 2px 4px; border: 1px solid rgba(0,224,122,0.22); border-radius: 4px;">v2.0</span>
        </div>
        ';
    } elseif ($variant === 'navbar') {
        $html .= '
        <a href="#" class="nav-logo">
            <div class="logo-mark"><i class="fa-solid fa-gamepad"></i></div>
            Respawn Logics
            <span class="version-pill">v2.0</span>
        </a>
        ';
    }
    
    return $html;
}
