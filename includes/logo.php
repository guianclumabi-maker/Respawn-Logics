<?php

function renderLogo($variant = 'centered') {
    $html = '';
    if ($variant === 'centered') {
        $html .= '
        <div class="logo text-center" style="display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 12px; margin-bottom: 24px;">
            <div style="width: 48px; height: 48px; background: linear-gradient(135deg, #00e07a, #00b8ff); border-radius: 12px; display: flex; align-items: center; justify-content: center; box-shadow: 0 0 20px rgba(0,224,122,0.4);">
                <i class="fa-solid fa-gamepad" style="color: #000; font-size: 26px;"></i>
            </div>
            <div style="display: flex; align-items: baseline; gap: 8px;">
                <span style="font-family: \'JetBrains Mono\', monospace; font-size: 24px; font-weight: 700; color: var(--text-primary); letter-spacing: -0.5px; margin: 0;">Respawn Logics</span>
                <span style="font-family: \'JetBrains Mono\', monospace; font-size: 10px; font-weight: 700; color: #00e07a;">v2.1</span>
            </div>
        </div>
        ';
    } elseif ($variant === 'sidebar') {
        $html .= '
        <div style="display:flex; align-items:center; gap:10px;">
            <div style="width:40px; height:40px; border-radius:10px; background:linear-gradient(135deg, #00e07a, #00b8ff); display:flex; align-items:center; justify-content:center; flex-shrink:0; box-shadow:0 8px 20px rgba(0,224,122,0.25);">
                <i class="fa-solid fa-gamepad" style="color: #000; font-size: 22px;"></i>
            </div>
            <div style="display:flex; align-items:baseline; gap:5px;">
                <span class="brand-text" style="font-family:\'JetBrains Mono\', monospace; font-weight:700; font-size:15px; letter-spacing:-0.5px; white-space:nowrap; color:var(--text-primary);">Respawn Logics</span>
                <span style="font-family:\'JetBrains Mono\', monospace; font-size:9px; font-weight:700; color:#00e07a;">v2.1</span>
            </div>
        </div>
        ';
    } elseif ($variant === 'navbar') {
        $html .= '
        <a href="#" class="nav-logo" style="align-items:baseline;">
            <div class="logo-mark">
                <i class="fa-solid fa-gamepad" style="color: #000; font-size: 22px;"></i>
            </div>
            <div style="display:flex; align-items:baseline; gap:5px;">
                <span>Respawn Logics</span>
                <span class="version-pill">v2.1</span>
            </div>
        </a>
        ';
    }
    
    return $html;
}
