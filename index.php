<?php
require_once __DIR__ . '/bootstrap/app.php';

$loggedIn = isLoggedIn() && (!isset($_SESSION['must_change_password']) || $_SESSION['must_change_password'] !== true);
?>
<!DOCTYPE html>
<html lang="en" style="scroll-behavior: smooth;">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Respawn Logics — HR That Levels Up Your Team</title>
    <meta name="description" content="Respawn Logics is the enterprise HR platform built for companies that think differently. Payroll, ATS, performance, and more — all in one respawn point.">
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@300;400;500;600;700&family=Archivo:wght@700;800;900&family=JetBrains+Mono:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
    <script src="https://unpkg.com/lucide@latest" defer></script>
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            lucide.createIcons();
        });
    </script>
    <link rel="icon" type="image/svg+xml" href="<?= url('/assets/favicon.svg') ?>">
    <style>
        :root {
            --green:     #00e07a;
            --green-dim: #00b862;
            --amber:     #f5a623;
            --blue:      #4f8ef7;
            --purple:    #9b6dff;
            --red:       #ff4d6a;
            --teal:      #00c9b1;

            /* Background: deep navy-slate, not pure black */
            --bg:        #0b0f1a;
            --bg2:       #0f1422;
            --bg3:       #141929;
            --bg4:       #1a2035;

            --border:    rgba(0, 224, 122, 0.1);
            --border2:   rgba(255, 255, 255, 0.07);
            --border3:   rgba(255, 255, 255, 0.04);

            --text:      #c8d0e0;
            --text-dim:  #5e6a82;
            --text-mid:  #8b95a8;

            --mono:      'JetBrains Mono', monospace;
            --sans:      'Space Grotesk', sans-serif;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }
        html { scroll-behavior: smooth; }

        body {
            background-color: var(--bg);
            font-family: var(--sans);
            color: var(--text);
            line-height: 1.5;
            -webkit-font-smoothing: antialiased;
        }

        /* Layered background — navy base with dynamic drifting grid */
        .global-bg {
            position: fixed;
            top: 0; left: 0; width: 100vw; height: 100vh;
            z-index: 0;
            pointer-events: none;
            background-color: var(--bg);
            background-image:
                radial-gradient(ellipse 90% 55% at 50% -5%, rgba(255,255,255,0.015) 0%, transparent 65%),
                radial-gradient(ellipse 60% 50% at 85% 110%, rgba(255,255,255,0.01) 0%, transparent 65%);
        }

        /* Subtle noise texture */
        body::after {
            content: '';
            position: fixed;
            inset: 0;
            opacity: 0.018;
            background-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 256 256' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)'/%3E%3C/svg%3E");
            background-size: 128px 128px;
            pointer-events: none;
            z-index: 9998;
        }

        /* ─── NAV ─── */
        nav {
            position: fixed;
            top: 0; left: 0; right: 0;
            z-index: 1000;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 48px;
            height: 62px;
            background: rgba(11, 15, 26, 0.88);
            backdrop-filter: blur(20px);
            border-bottom: 1px solid var(--border2);
        }

        .nav-logo {
            display: flex;
            align-items: center;
            gap: 10px;
            text-decoration: none;
            font-family: var(--mono);
            font-size: 0.9375rem;
            font-weight: 700;
            color: var(--text);
        }

        .logo-mark {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, var(--green), #00b8ff);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #000;
            box-shadow: 0 8px 20px rgba(0, 224, 122, 0.25);
            flex-shrink: 0;
        }

        .version-pill {
            font-family: var(--mono);
            font-size: 0.5625rem;
            font-weight: 700;
            color: var(--green);
        }

        .nav-links {
            display: flex;
            align-items: center;
            gap: 36px;
        }

        .nav-links a {
            font-size: 0.875rem;
            color: var(--text-dim);
            text-decoration: none;
            font-weight: 500;
            transition: color 0.2s;
        }

        .nav-links a:hover { color: var(--text); }

        .nav-cta {
            font-family: var(--mono);
            font-size: 0.8rem;
            font-weight: 700;
            letter-spacing: 0.04em;
            color: #000 !important;
            background: var(--green);
            padding: 9px 20px;
            border-radius: 5px;
            text-decoration: none;
            transition: all 0.2s;
        }

        .nav-cta:hover {
            background: #fff;
            transform: translateY(-1px);
            box-shadow: 0 4px 16px rgba(0,224,122,0.3);
        }

        /* ─── NEW HERO (HUGO STYLE) ─── */
        .hero {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            justify-content: center;
            padding: 120px 5% 80px;
            position: relative;
            overflow: hidden;
            background: #08090f;
        }
        /* Soft, glowing colorful gradients bleeding from the edges */
        .hero::before {
            content: '';
            position: absolute;
            top: -10%;
            left: -10%;
            width: 50%;
            height: 60%;
            background: radial-gradient(circle, rgba(179,34,81,0.02) 0%, transparent 70%);
            filter: blur(80px);
            pointer-events: none;
            z-index: 1;
        }
        .hero::after {
            content: '';
            position: absolute;
            bottom: -10%;
            right: -10%;
            width: 60%;
            height: 70%;
            background: radial-gradient(circle, rgba(0,224,122,0.015) 0%, rgba(138,43,226,0.02) 50%, transparent 80%);
            filter: blur(100px);
            pointer-events: none;
            z-index: 1;
        }
        .hero-glow-mid {
            position: absolute;
            top: 40%;
            left: 30%;
            width: 40%;
            height: 50%;
            background: radial-gradient(circle, rgba(37,99,235,0.02) 0%, transparent 60%);
            filter: blur(90px);
            pointer-events: none;
            z-index: 1;
        }
        .hero-inner {
            position: relative;
            z-index: 2;
            max-width: 100%;
            width: 100%;
            margin: 0 auto;
            display: flex;
            flex-direction: column;
            flex: 1;
            justify-content: center;
        }
        .hero-status {
            display: flex;
            align-items: center;
            gap: 8px;
            font-family: var(--mono);
            font-size: 0.72rem;
            color: var(--text-dim);
            text-transform: uppercase;
            letter-spacing: 0.15em;
            margin-bottom: 24px;
        }
        .hero-status-dot {
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background: var(--green);
            box-shadow: 0 0 10px var(--green);
            animation: hero-status-pulse 2s ease infinite;
        }
        @keyframes hero-status-pulse {
            0%, 100% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.6; transform: scale(1.15); }
        }
        .hero-h1-new {
            font-size: clamp(3.5rem, 8vw, 7.5rem);
            font-weight: 800;
            line-height: 0.95;
            letter-spacing: -0.04em;
            color: #fff;
            margin-bottom: 56px;
            font-family: var(--sans);
            text-align: left;
        }
        .hero-h1-new .gradient-text {
            background: linear-gradient(90deg, #a3f7bf 0%, #ff839a 50%, #9e72f9 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            display: inline-block;
        }
        .cursor-blink {
            display: inline-block;
            font-weight: 800;
            animation: cursorBlinkColors 1.2s step-end infinite;
            margin-left: 4px;
            vertical-align: baseline;
        }
        @keyframes cursorBlinkColors {
            0%   { color: #a3f7bf; opacity: 1; }
            25%  { color: #a3f7bf; opacity: 0; }
            33%  { color: #ff839a; opacity: 1; }
            58%  { color: #ff839a; opacity: 0; }
            66%  { color: #9e72f9; opacity: 1; }
            91%  { color: #9e72f9; opacity: 0; }
            100% { color: #a3f7bf; opacity: 1; }
        }
        .hero-bottom-layout {
            display: grid;
            grid-template-columns: 1.2fr 1fr;
            gap: 40px;
            align-items: flex-end;
            margin-top: 24px;
        }
        .hero-sub-new {
            font-size: 1.05rem;
            line-height: 1.7;
            color: var(--text-mid);
            max-width: 620px;
            text-align: left;
        }
        .hero-actions-new {
            display: flex;
            flex-direction: row;
            align-items: center;
            gap: 20px;
            justify-content: flex-end;
        }
        .btn-neon-pill {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            background: var(--green);
            color: #000;
            font-family: var(--sans);
            font-weight: 600;
            font-size: 1.1rem;
            padding: 16px 36px;
            border-radius: 999px;
            border: none;
            cursor: pointer;
            transition: transform 0.2s, box-shadow 0.2s;
            text-decoration: none;
            width: fit-content;
        }
        .btn-neon-pill:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 24px rgba(0,224,122,0.35);
        }
        .btn-outline-pill {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            background: transparent;
            color: #fff;
            font-family: var(--sans);
            font-weight: 500;
            font-size: 1.1rem;
            padding: 16px 36px;
            border-radius: 999px;
            border: 1px solid rgba(255,255,255,0.15);
            cursor: pointer;
            transition: background 0.2s, border-color 0.2s;
            text-decoration: none;
            width: fit-content;
        }
        .btn-outline-pill:hover {
            background: rgba(255,255,255,0.04);
            border-color: rgba(255,255,255,0.3);
        }
        .hero-scroll-indicator {
            position: absolute;
            bottom: 32px;
            left: 50%;
            transform: translateX(-50%);
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 10px;
            font-family: var(--mono);
            font-size: 0.62rem;
            letter-spacing: 0.2em;
            color: var(--text-dim);
            text-transform: uppercase;
            pointer-events: none;
            z-index: 2;
        }
        .hero-scroll-line {
            width: 1px;
            height: 32px;
            background: linear-gradient(to bottom, var(--text-dim), transparent);
            opacity: 0.3;
        }
        @media (max-width: 992px) {
            .hero { padding: 120px 40px 80px; }
            .hero-bottom-layout { grid-template-columns: 1fr; gap: 32px; }
            .hero-actions-new { flex-direction: column; align-items: flex-start; }
        }
        @media (max-width: 576px) {
            .hero { padding: 100px 24px 60px; }
        }

        .btn-primary {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: var(--green);
            color: #000;
            font-family: var(--mono);
            font-weight: 700;
            font-size: 0.9rem;
            padding: 13px 28px;
            border-radius: 6px;
            text-decoration: none;
            letter-spacing: 0.03em;
            transition: all 0.2s;
        }

        .btn-primary:hover {
            background: #fff;
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(0,224,122,0.2);
        }

        .btn-ghost {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: transparent;
            color: var(--text-mid);
            font-family: var(--mono);
            font-size: 0.875rem;
            font-weight: 500;
            padding: 13px 24px;
            border-radius: 6px;
            border: 1px solid var(--border2);
            text-decoration: none;
            transition: all 0.2s;
        }

        .btn-ghost:hover {
            border-color: rgba(255,255,255,0.18);
            color: var(--text);
        }

        /* HUD Stats */
        .hud {
            position: relative;
            z-index: 2;
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            max-width: 860px;
            width: 100%;
            border: 1px solid var(--border2);
            border-radius: 10px;
            overflow: hidden;
            background: rgba(15,20,34,0.8);
            backdrop-filter: blur(10px);
        }

        .hud-item {
            padding: 22px 28px;
            border-right: 1px solid var(--border2);
            text-align: left;
        }

        .hud-item:last-child { border-right: none; }

        .hud-key {
            font-family: var(--mono);
            font-size: 0.625rem;
            letter-spacing: 0.12em;
            text-transform: uppercase;
            color: var(--text-dim);
            margin-bottom: 8px;
        }

        .hud-val {
            font-family: var(--mono);
            font-size: 1.75rem;
            font-weight: 700;
            line-height: 1;
            color: var(--green);
        }

        .hud-val.amber { color: var(--amber); }
        .hud-val.blue  { color: var(--blue);  }

        .hud-desc {
            font-size: 0.75rem;
            color: var(--text-dim);
            margin-top: 5px;
        }

        /* ─── MODULES SECTION ─── */
        .section {
            display: flex;
            flex-direction: column;
            justify-content: center;
            max-width: 1180px;
            margin: 0 auto;
            padding: 128px 24px;
        }

        .eyebrow {
            font-family: var(--mono);
            font-size: 0.6875rem;
            color: var(--green);
            letter-spacing: 0.14em;
            text-transform: uppercase;
            font-weight: 600;
            margin-bottom: 14px;
        }

        .section-h {
            font-size: clamp(1.875rem, 4vw, 2.875rem);
            font-weight: 700;
            color: var(--text);
            letter-spacing: -0.03em;
            line-height: 1.12;
            margin-bottom: 14px;
        }

        .section-p {
            font-size: 1rem;
            color: var(--text-mid);
            max-width: 500px;
            line-height: 1.7;
            margin-bottom: 56px;
        }

        /* CATEGORY TABS */
        .module-tabs {
            display: flex;
            gap: 8px;
            margin-bottom: 40px;
            flex-wrap: wrap;
        }

        .tab-btn {
            font-family: var(--mono);
            font-size: 0.75rem;
            font-weight: 600;
            letter-spacing: 0.06em;
            padding: 7px 16px;
            border-radius: 5px;
            border: 1px solid var(--border2);
            background: transparent;
            color: var(--text-dim);
            cursor: pointer;
            transition: all 0.2s;
        }

        .tab-btn:hover, .tab-btn.active {
            background: var(--bg4);
            border-color: rgba(255,255,255,0.15);
            color: var(--text);
        }

        /* MODULE CARDS GRID */
        .module-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 14px;
        }

        .mod-card {
            background: rgba(255, 255, 255, 0.02);
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
            border: 1px solid var(--border2);
            border-radius: 10px;
            padding: 26px 28px;
            position: relative;
            overflow: hidden;
            animation: pulse-glow 4s infinite alternate;
            transition: border-color 0.2s, transform 0.2s, box-shadow 0.2s;
        }

        .mod-card::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 2px;
            opacity: 0;
            transition: opacity 0.2s;
        }

        .mod-card.c-green::before  { background: var(--green); }
        .mod-card.c-blue::before   { background: var(--blue); }
        .mod-card.c-purple::before { background: var(--purple); }
        .mod-card.c-amber::before  { background: var(--amber); }
        .mod-card.c-teal::before   { background: var(--teal); }
        .mod-card.c-red::before    { background: var(--red); }

        .mod-card:hover {
            border-color: rgba(255,255,255,0.12);
            transform: translateY(-3px);
            box-shadow: 0 12px 32px rgba(0,0,0,0.3);
        }

        .mod-card:hover::before { opacity: 1; }

        .mod-card.wide { grid-column: span 2; }

        .mod-icon {
            width: 40px;
            height: 40px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1rem;
            margin-bottom: 18px;
        }

        .mod-icon.green  { background: rgba(0,224,122,0.1);  color: var(--green); }
        .mod-icon.blue   { background: rgba(79,142,247,0.1); color: var(--blue); }
        .mod-icon.purple { background: rgba(155,109,255,0.1);color: var(--purple); }
        .mod-icon.amber  { background: rgba(245,166,35,0.1); color: var(--amber); }
        .mod-icon.teal   { background: rgba(0,201,177,0.1);  color: var(--teal); }
        .mod-icon.red    { background: rgba(255,77,106,0.1); color: var(--red); }

        .mod-tag {
            display: inline-block;
            font-family: var(--mono);
            font-size: 0.625rem;
            font-weight: 700;
            letter-spacing: 0.1em;
            padding: 2px 8px;
            border-radius: 3px;
            margin-bottom: 10px;
        }

        .mod-tag.green  { color: var(--green);  background: rgba(0,224,122,0.08);  border: 1px solid rgba(0,224,122,0.15); }
        .mod-tag.blue   { color: var(--blue);   background: rgba(79,142,247,0.08); border: 1px solid rgba(79,142,247,0.15); }
        .mod-tag.purple { color: var(--purple); background: rgba(155,109,255,0.08);border: 1px solid rgba(155,109,255,0.15); }
        .mod-tag.amber  { color: var(--amber);  background: rgba(245,166,35,0.08); border: 1px solid rgba(245,166,35,0.15); }
        .mod-tag.teal   { color: var(--teal);   background: rgba(0,201,177,0.08);  border: 1px solid rgba(0,201,177,0.15); }
        .mod-tag.red    { color: var(--red);     background: rgba(255,77,106,0.08); border: 1px solid rgba(255,77,106,0.15); }

        .mod-title {
            font-size: 1.0625rem;
            font-weight: 600;
            color: var(--text);
            letter-spacing: -0.02em;
            margin-bottom: 8px;
        }

        .mod-desc {
            font-size: 0.875rem;
            color: var(--text-mid);
            line-height: 1.65;
        }

        .mod-badge {
            position: absolute;
            bottom: 18px;
            right: 20px;
            font-family: var(--mono);
            font-size: 0.625rem;
            font-weight: 700;
            letter-spacing: 0.08em;
            color: var(--text-dim);
            opacity: 0.4;
        }

        /* XP bar */
        .xp {
            margin-top: 18px;
        }

        .xp-head {
            display: flex;
            justify-content: space-between;
            font-family: var(--mono);
            font-size: 0.625rem;
            color: var(--text-dim);
            margin-bottom: 5px;
        }

        .xp-track {
            height: 3px;
            background: rgba(255,255,255,0.05);
            border-radius: 2px;
        }

        .xp-fill {
            height: 100%;
            border-radius: 2px;
        }

        /* ─── DIVIDER ─── */
        .divider {
            border: none;
            margin: 80px 0;
        }

        /* ─── PHILOSOPHY SECTION ─── */
        .story-section {
            display: flex;
            flex-direction: column;
            justify-content: center;
            padding: 100px 24px;
            width: 100%;
            text-align: center;
            position: relative;
        }

        .story-inner {
            max-width: 1180px;
            margin: 0 auto;
            padding: 128px 24px;
            display: grid;
            grid-template-columns: 1fr 420px;
            gap: 80px;
            align-items: center;
        }

        .terminal {
            background: var(--bg);
            border: 1px solid var(--border);
            border-radius: 10px;
            overflow: hidden;
            font-family: var(--mono);
        }

        .term-bar {
            display: flex;
            align-items: center;
            gap: 7px;
            padding: 11px 16px;
            border-bottom: 1px solid var(--border);
            background: var(--bg3);
        }

        .t-dot { width: 10px; height: 10px; border-radius: 50%; }
        .t-dot.r { background: #ff5f57; }
        .t-dot.y { background: #febc2e; }
        .t-dot.g { background: #28c840; }

        .term-file {
            font-size: 0.7rem;
            color: var(--text-dim);
            margin-left: 6px;
        }

        .term-body {
            padding: 22px 20px;
            font-size: 0.8rem;
            line-height: 2.1;
        }

        .t-row { display: flex; gap: 10px; }
        .t-p { color: var(--green); flex-shrink: 0; }
        .t-c { color: var(--text); }
        .t-o { color: var(--text-dim); padding-left: 18px; font-size: 0.75rem; }
        .t-cm { color: #354050; }
        .t-v { color: var(--amber); }
        .t-gap { height: 0.4rem; }
        .t-cursor { display: inline-block; width: 8px; height: 13px; background: var(--green); animation: blink 1.1s step-start infinite; vertical-align: middle; }

        /* 🎮 BETA CTA 🎮 */
        .beta-wrap {
            display: flex;
            flex-direction: column;
            justify-content: center;
            max-width: 1400px;
            margin: 0 auto;
            padding: 110px 24px 60px;
        }

        .beta-card {
            background: rgba(255, 255, 255, 0.02);
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
            border: 1px solid var(--border2);
            border-radius: 14px;
            padding: 64px 60px;
            display: grid;
            grid-template-columns: 1fr 240px;
            gap: 60px;
            align-items: center;
            position: relative;
            overflow: hidden;
            animation: pulse-glow 5s infinite alternate;
        }

        .beta-card::before {
            content: '';
            position: absolute;
            top: -80px; right: -80px;
            width: 280px; height: 280px;
            background: radial-gradient(circle, rgba(0,224,122,0.07) 0%, transparent 65%);
            pointer-events: none;
        }

        .beta-card::after {
            content: '';
            position: absolute;
            bottom: -60px; left: 30%;
            width: 200px; height: 200px;
            background: radial-gradient(circle, rgba(79,142,247,0.05) 0%, transparent 65%);
            pointer-events: none;
        }

        .beta-label {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            font-family: var(--mono);
            font-size: 0.6875rem;
            font-weight: 700;
            letter-spacing: 0.1em;
            color: var(--amber);
            background: rgba(245,166,35,0.07);
            border: 1px solid rgba(245,166,35,0.2);
            padding: 4px 12px;
            border-radius: 3px;
            margin-bottom: 22px;
        }

        .beta-h {
            font-size: 2.25rem;
            font-weight: 700;
            color: var(--text);
            letter-spacing: -0.03em;
            line-height: 1.15;
            margin-bottom: 16px;
        }

        .beta-p {
            font-size: 0.9375rem;
            color: var(--text-mid);
            line-height: 1.75;
            max-width: 480px;
            margin-bottom: 28px;
        }

        .perks {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px 24px;
        }

        .perk {
            display: flex;
            align-items: center;
            gap: 9px;
            font-size: 0.875rem;
            color: var(--text);
        }

        .perk i {
            color: var(--green);
            font-size: 0.8rem;
            flex-shrink: 0;
        }

        .price-panel {
            text-align: center;
            position: relative;
            z-index: 1;
        }

        .price-num {
            font-family: var(--mono);
            font-size: 4rem;
            font-weight: 700;
            color: var(--green);
            line-height: 1;
        }

        .price-tag {
            font-family: var(--mono);
            font-size: 0.6875rem;
            color: var(--text-dim);
            letter-spacing: 0.08em;
            margin-top: 6px;
            margin-bottom: 28px;
        }

        /* 🎮 FOOTER 🎮 */
        footer {
            position: relative;
            z-index: 10;
            background: var(--bg);
            border-top: 1px solid var(--border2);
            padding: 60px 0 30px;
        }

        .footer-inner {
            max-width: 1600px;
            width: 95%;
            margin: 0 auto;
            padding: 0 24px;
            display: flex;
            flex-direction: column;
            gap: 60px;
        }

        .footer-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 40px;
        }

        .footer-col h4 {
            font-family: var(--sans);
            font-size: 0.9rem;
            font-weight: 600;
            color: var(--text);
            margin-bottom: 20px;
        }

        .footer-col ul {
            list-style: none;
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .footer-col a {
            font-family: var(--sans);
            font-size: 0.85rem;
            color: var(--text-dim);
            text-decoration: none;
            transition: color 0.2s;
        }

        .footer-col a:hover {
            color: var(--text);
        }

        .footer-bottom {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 30px;
            border-top: 1px solid var(--border2);
        }

        .footer-copy {
            font-family: var(--mono);
            font-size: 0.8rem;
            color: var(--text-dim);
        }

        .footer-socials {
            display: flex;
            gap: 20px;
        }

        .footer-socials a {
            color: var(--text-dim);
            text-decoration: none;
            font-size: 1.1rem;
            transition: color 0.2s;
        }

        .footer-socials a:hover { color: var(--text); }

        /* ─── RESPONSIVE ─── */
        @media (max-width: 960px) {
            nav { padding: 0 20px; }
            .nav-links a:not(.nav-cta) { display: none; }
            .hero h1 { font-size: 3.5rem; }
            .terminal-window { min-height: 400px; }
            .module-grid { grid-template-columns: repeat(2, 1fr); }
            .mod-card.wide { grid-column: span 2; }
            .story-inner { grid-template-columns: 1fr; gap: 40px; }
            .beta-card { grid-template-columns: 1fr; padding: 36px 28px; }
            .perks { grid-template-columns: 1fr; }
            .price-panel { text-align: left; }
            .footer-bottom { flex-direction: column; gap: 16px; text-align: center; }
        }

        @media (max-width: 600px) {
            .module-grid { grid-template-columns: 1fr; }
            .mod-card.wide { grid-column: span 1; }
            .hero-h1 { font-size: 2.75rem; }
            .hud { grid-template-columns: 1fr 1fr; }
        }
        /* ─── NEW STORY SECTIONS ─── */
        .story-section {
            display: flex;
            flex-direction: column;
            justify-content: center;
            padding: 100px 24px;
            width: 100%;
            text-align: center;
            position: relative;
        }
        .story-container {
            max-width: 1600px;
            width: 95%;
            margin: 0 auto;
            position: relative;
            background: rgba(255, 255, 255, 0.02);
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
            border: 1px solid rgba(255, 255, 255, 0.05);
            border-radius: 24px;
            padding: 120px 80px;
            box-shadow: 0 30px 60px rgba(0, 0, 0, 0.4);
            animation: pulse-glow 6s infinite alternate;
        }
        @media (max-width: 768px) {
            .story-container { padding: 40px 20px; }
        }
        .story-section h2 {
            font-size: clamp(2rem, 5vw, 3.25rem);
            color: var(--text);
            margin-bottom: 24px;
            line-height: 1.15;
            letter-spacing: -0.02em;
        }
        .story-section p.sub {
            font-size: 1.2rem;
            color: var(--text-mid);
            max-width: 800px;
            margin: 0 auto 40px;
            line-height: 1.7;
        }

        /* Journey Flow */
        .journey-flow {
            display: flex;
            align-items: center;
            justify-content: center;
            flex-wrap: wrap;
            gap: 16px;
            margin-top: 40px;
            position: relative;
        }
        .journey-node {
            background: rgba(0,224,122,0.05);
            border: 1px solid rgba(0,224,122,0.2);
            padding: 14px 28px;
            border-radius: 50px;
            color: var(--green);
            font-family: var(--mono);
            font-weight: 600;
            font-size: 0.9375rem;
            display: flex;
            align-items: center;
            gap: 10px;
            box-shadow: 0 4px 20px rgba(0,224,122,0.05);
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .journey-node:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 24px rgba(0,224,122,0.15);
            border-color: rgba(0,224,122,0.4);
        }
        .journey-node i {
            font-size: 1.1em;
            opacity: 0.8;
        }
        .journey-arrow {
            color: var(--text-dim);
            font-size: 1rem;
            opacity: 0.5;
        }

        /* Problems Grid */
        .problems-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 24px;
            margin-bottom: 56px;
        }
        @media (max-width: 1024px) {
            .problems-grid { grid-template-columns: repeat(2, 1fr); }
        }
        @media (max-width: 768px) {
            .problems-grid { grid-template-columns: 1fr; }
        }
        .problem-card {
            background: rgba(255,77,106,0.05);
            border: 1px solid rgba(255,77,106,0.15);
            border-radius: 10px;
            padding: 24px;
            text-align: left;
            color: var(--red);
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 16px;
        }
        .problem-card i { font-size: 1.25rem; }
        .solution-block {
            background: linear-gradient(135deg, rgba(0,224,122,0.1), rgba(0,184,255,0.05));
            border: 1px solid rgba(0,224,122,0.3);
            border-radius: 12px;
            padding: 32px;
            font-size: 1.25rem;
            color: var(--text);
            font-weight: 600;
            max-width: 800px;
            margin: 0 auto;
        }

        /* 4 Pillars */
        .pillars-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 24px;
        }
        @media (max-width: 1024px) {
            .pillars-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        @media (max-width: 768px) {
            .pillars-grid {
                grid-template-columns: 1fr;
            }
        }
        .pillar-card {
            background: rgba(255, 255, 255, 0.02);
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
            border: 1px solid var(--border2);
            border-radius: 12px;
            padding: 32px 24px;
            text-align: left;
            animation: pulse-glow 4.5s infinite alternate;
        }
        .pillar-card h3 {
            font-size: 1.25rem;
            color: var(--text);
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .pillar-card h3 i { color: var(--green); }
        .pillar-card ul {
            list-style: none;
            color: var(--text-mid);
            font-size: 0.9375rem;
        }
        .pillar-card ul li {
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .pillar-card ul li::before {
            content: '\f054';
            font-family: 'Font Awesome 6 Free';
            font-weight: 900;
            font-size: 0.625rem;
            color: var(--green);
        }

        /* Why Teams Choose */
        .why-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 24px;
        }
        @media (max-width: 768px) { .why-grid { grid-template-columns: 1fr; } }
        .why-card {
            background: transparent;
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
            border: 1px solid rgba(255, 255, 255, 0.03);
            border-radius: 12px;
            padding: 40px 32px;
            text-align: left;
            transition: transform 0.3s, border-color 0.3s;
            animation: pulse-glow 5.5s infinite alternate;
        }
        .why-card:hover { transform: translateY(-3px); border-color: rgba(0,224,122,0.3); }
        .why-card i {
            font-size: 2rem;
            color: var(--green);
            margin-bottom: 20px;
        }
        .why-card h3 {
            font-size: 1.375rem;
            color: var(--text);
            margin-bottom: 12px;
        }
        .why-card p {
            color: var(--text-mid);
            line-height: 1.6;
        }

        /* Split Section */
        .split-section {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 60px;
            align-items: center;
            text-align: left;
        }
        @media (max-width: 900px) { .split-section { grid-template-columns: 1fr; text-align: center; } }
        .split-image img {
            width: 100%;
            border-radius: 12px;
            border: 1px solid var(--border2);
            box-shadow: 0 20px 40px rgba(0,0,0,0.4);
        }

        /* Mock Window Screenshot */
        .window-frame {
            border: 1px solid var(--border2);
            border-radius: 6px;
            overflow: hidden;
            background: #060913;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5);
            width: 100%;
        }
        .window-header {
            background: #111524;
            border-bottom: 1px solid var(--border3);
            height: 36px;
            display: flex;
            align-items: center;
            padding: 0 16px;
            position: relative;
        }
        .window-dots {
            display: flex;
            gap: 6px;
        }
        .window-dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: rgba(255,255,255,0.1);
        }
        .window-dot.close { background: #ff4d6a; }
        .window-dot.minimize { background: #f5a623; }
        .window-dot.maximize { background: #00e07a; }
        .window-url {
            position: absolute;
            left: 50%;
            transform: translateX(-50%);
            font-family: var(--mono);
            font-size: 0.7rem;
            color: var(--text-dim);
            background: #0b0f1a;
            border: 1px solid var(--border3);
            padding: 2px 30px;
            border-radius: 4px;
            text-align: center;
            white-space: nowrap;
            max-width: 60%;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .window-body {
            position: relative;
            background: #0b0f1a;
            display: block;
        }
        .window-screenshot {
            display: block;
            width: 100%;
            height: auto;
            max-height: 480px;
            object-fit: cover;
            object-position: top center;
            border: none;
            transition: opacity 0.3s;
        }

        /* Gaming CTA */
        .gaming-cta {
            padding: 60px 24px;
            width: 100%;
            text-align: center;
            position: relative;
        }
        .gaming-cta .story-container {
            background: linear-gradient(135deg, rgba(0, 224, 122, 0.05), rgba(0, 224, 122, 0.01));
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
            border-color: rgba(0, 224, 122, 0.2);
        }
        .gaming-flow {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 24px;
            flex-wrap: wrap;
            margin-top: 40px;
        }
        .gaming-step {
            font-family: var(--mono);
            font-size: 1.125rem;
            font-weight: 700;
            color: var(--text);
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .gaming-step i { color: var(--green); }
        .gaming-divider { color: var(--text-dim); }

        /* ─── REVAMP RAILWAY/CURSOR LAYOUTS ─── */
        .timeline-section {
            position: relative;
            padding: 120px 24px;
            width: 100%;
        }
        .timeline-line {
            position: absolute;
            left: 50px;
            top: 0;
            bottom: 0;
            width: 2px;
            background: var(--green);
            opacity: 0.15;
        }
        .timeline-line.orange {
            background: var(--amber);
        }
        .timeline-line.blue {
            background: var(--blue);
        }
        .fixed-timeline-node {
            position: fixed;
            left: 43px;
            top: 50vh;
            width: 16px;
            height: 16px;
            border-radius: 50%;
            border: 3px solid var(--bg);
            background: var(--green);
            box-shadow: 0 0 12px var(--green);
            z-index: 999;
            transform: translateY(-50%);
            display: none;
            transition: background 0.3s ease, box-shadow 0.3s ease;
        }

        .revamp-container {
            max-width: 1400px;
            width: 95%;
            margin: 0 auto;
            position: relative;
            padding-left: 100px;
        }

        @media (max-width: 768px) {
            .timeline-line { display: none; }
            .fixed-timeline-node { display: none; }
            .revamp-container { padding-left: 0; }
        }

        .revamp-grid {
            display: grid;
            grid-template-columns: 45% 55%;
            gap: 60px;
            align-items: center;
            margin-bottom: 140px;
            text-align: left;
        }
        .revamp-grid.reverse {
            grid-template-columns: 55% 45%;
        }
        @media (max-width: 960px) {
            .revamp-grid, .revamp-grid.reverse {
                grid-template-columns: 1fr;
                gap: 40px;
                margin-bottom: 80px;
            }
        }

        /* Pill Labels */
        .pill-label {
            display: inline-block;
            font-family: var(--mono);
            font-size: 0.7rem;
            font-weight: 600;
            color: var(--green);
            background: rgba(0, 224, 122, 0.06);
            border: 1px solid rgba(0, 224, 122, 0.15);
            padding: 4px 12px;
            border-radius: 4px;
            margin-bottom: 20px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        .pill-label.orange {
            color: var(--amber);
            background: rgba(245, 166, 35, 0.06);
            border-color: rgba(245, 166, 35, 0.15);
        }
        .pill-label.blue {
            color: var(--blue);
            background: rgba(79, 142, 247, 0.06);
            border-color: rgba(79, 142, 247, 0.15);
        }

        /* Railway Canvas Grid & Services */
        .railway-canvas {
            position: relative;
            background: rgba(255,255,255,0.01);
            border: 1px solid var(--border3);
            border-radius: 16px;
            height: 380px;
            box-shadow: inset 0 0 30px rgba(0,0,0,0.5);
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 100%;
        }
        /* Grid background for canvas */
        .railway-canvas::before {
            content: '';
            position: absolute;
            inset: 0;
            background-image: linear-gradient(rgba(255,255,255,0.02) 1px, transparent 1px), linear-gradient(90deg, rgba(255,255,255,0.02) 1px, transparent 1px);
            background-size: 24px 24px;
            pointer-events: none;
        }
        .canvas-card {
            background: #111422;
            border: 1px solid var(--border2);
            border-radius: 12px;
            padding: 16px 20px;
            width: 250px;
            box-shadow: 0 15px 40px rgba(0,0,0,0.6);
            position: absolute;
            transition: transform 0.3s;
            text-align: left;
        }
        .canvas-card:hover {
            transform: translateY(-4px);
            border-color: rgba(255,255,255,0.15);
        }
        .canvas-card .dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: var(--green);
            display: inline-block;
            margin-right: 8px;
            box-shadow: 0 0 8px var(--green);
        }
        .canvas-card .card-host {
            font-family: var(--mono);
            font-size: 0.7rem;
            color: var(--text-dim);
            margin-top: 4px;
        }
        .canvas-card .card-status {
            font-family: var(--mono);
            font-size: 0.7rem;
            color: var(--green);
            margin-top: 12px;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        /* List Items under titles */
        .revamp-list {
            margin-top: 32px;
            display: flex;
            flex-direction: column;
            gap: 20px;
        }
        .revamp-item {
            display: flex;
            align-items: flex-start;
            gap: 16px;
        }
        .revamp-item i {
            color: var(--green);
            font-size: 1.2rem;
            margin-top: 2px;
        }
        .revamp-item.orange i { color: var(--amber); }
        .revamp-item.blue i { color: var(--blue); }
        .revamp-item h4 {
            font-size: 1.05rem;
            font-weight: 600;
            color: var(--text);
            margin-bottom: 4px;
        }
        .revamp-item p {
            color: var(--text-mid);
            font-size: 0.9rem;
            line-height: 1.5;
        }

        /* Alternatives block */
        .alternatives-row {
            margin-top: 40px;
            border-top: 1px solid var(--border3);
            padding-top: 20px;
            display: flex;
            align-items: center;
            gap: 16px;
            font-size: 0.85rem;
            color: var(--text-dim);
        }
        .alternatives-row img {
            height: 18px;
            filter: brightness(0) invert(0.6);
            opacity: 0.7;
        }

        /* Observability Dashboard Mockup */
        .obs-dashboard {
            background: #080a13;
            border: 1px solid var(--border2);
            border-radius: 14px;
            overflow: hidden;
            box-shadow: 0 30px 60px rgba(0,0,0,0.6);
            width: 100%;
        }
        .obs-header {
            background: #0f1324;
            border-bottom: 1px solid var(--border3);
            padding: 14px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-family: var(--mono);
            font-size: 0.75rem;
            color: var(--text-mid);
        }
        .obs-body {
            padding: 24px;
        }
        .obs-logs {
            background: #04060b;
            border: 1px solid var(--border3);
            border-radius: 8px;
            padding: 16px;
            font-family: var(--mono);
            font-size: 0.75rem;
            color: #d1d5db;
            line-height: 1.8;
            max-height: 180px;
            overflow-y: auto;
            text-align: left;
            margin-bottom: 24px;
            box-shadow: inset 0 2px 8px rgba(0,0,0,0.8);
        }
        .obs-logs .log-row {
            display: flex;
            gap: 12px;
        }
        .obs-logs .log-time { color: var(--text-dim); }
        .obs-logs .log-tag { color: var(--green); }
        .obs-logs .log-tag.warn { color: var(--amber); }
        .obs-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 16px;
        }
        @media (max-width: 768px) {
            .obs-grid { grid-template-columns: 1fr; }
        }
        .obs-chart {
            background: rgba(255,255,255,0.01);
            border: 1px solid var(--border3);
            border-radius: 8px;
            padding: 16px;
            text-align: left;
        }
        .obs-chart-title {
            font-family: var(--mono);
            font-size: 0.7rem;
            color: var(--text-dim);
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 12px;
        }

        /* Cursor Editor Agent Mockup */
        .editor-mockup {
            background: #0d0c15;
            border: 1px solid var(--border2);
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 30px 60px rgba(0,0,0,0.6);
            display: flex;
            height: 400px;
            width: 100%;
        }
        .editor-sidebar {
            width: 180px;
            background: #09080e;
            border-right: 1px solid var(--border3);
            padding: 16px;
            text-align: left;
            flex-shrink: 0;
        }
        .editor-sidebar-title {
            font-family: var(--mono);
            font-size: 0.65rem;
            color: var(--text-dim);
            text-transform: uppercase;
            margin-bottom: 12px;
        }
        .editor-sidebar-item {
            font-size: 0.75rem;
            color: var(--text-mid);
            padding: 6px 8px;
            border-radius: 4px;
            margin-bottom: 4px;
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
        }
        .editor-sidebar-item.active {
            background: rgba(255,255,255,0.04);
            color: #fff;
        }
        .editor-content {
            flex-grow: 1;
            padding: 24px;
            text-align: left;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }
        .agent-bubble {
            background: rgba(255,255,255,0.03);
            border: 1px solid var(--border3);
            border-radius: 10px;
            padding: 16px;
            font-size: 0.8rem;
            line-height: 1.6;
        }
        .agent-input-box {
            background: #14131d;
            border: 1px solid var(--border2);
            border-radius: 8px;
            padding: 12px 16px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            color: var(--text-dim);
            font-size: 0.75rem;
        }

        /* Changelog & History */
        .changelog-row {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin-top: 40px;
        }
        @media (max-width: 768px) {
            .changelog-row { grid-template-columns: 1fr; }
        }
        .changelog-card {
            background: rgba(255, 255, 255, 0.01);
            border: 1px solid var(--border3);
            border-radius: 12px;
            padding: 24px;
            text-align: left;
            transition: border-color 0.2s, transform 0.2s;
        }
        .changelog-card:hover {
            border-color: rgba(255,255,255,0.12);
            transform: translateY(-2px);
        }
        .changelog-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 16px;
        }
        .changelog-version {
            font-family: var(--mono);
            font-size: 0.7rem;
            font-weight: 700;
            color: var(--green);
            background: rgba(0,224,122,0.08);
            border: 1px solid rgba(0,224,122,0.15);
            padding: 2px 8px;
            border-radius: 4px;
        }
        .changelog-date {
            font-family: var(--mono);
            font-size: 0.75rem;
            color: var(--text-dim);
        }
        .changelog-title {
            font-size: 1.05rem;
            font-weight: 600;
            color: var(--text);
            margin-bottom: 8px;
        }
        .changelog-desc {
            color: var(--text-mid);
            font-size: 0.85rem;
            line-height: 1.5;
        }

        /* ─── LOGO BAR (SOCIAL PROOF) ─── */
        .logo-bar {
            padding: 40px 0;
            border-top: 1px solid var(--border3);
            border-bottom: 1px solid var(--border3);
            overflow: hidden;
            position: relative;
        }
        .logo-bar::before, .logo-bar::after {
            content: '';
            position: absolute;
            top: 0; bottom: 0;
            width: 120px;
            z-index: 2;
        }
        .logo-bar::before { left: 0; background: linear-gradient(90deg, var(--bg), transparent); }
        .logo-bar::after  { right: 0; background: linear-gradient(270deg, var(--bg), transparent); }
        .logo-track {
            display: flex;
            gap: 60px;
            animation: scroll-logos 28s linear infinite;
            width: max-content;
        }
        @keyframes scroll-logos {
            from { transform: translateX(0); }
            to   { transform: translateX(-50%); }
        }
        .logo-item {
            display: flex;
            align-items: center;
            gap: 10px;
            font-family: var(--mono);
            font-size: 0.85rem;
            font-weight: 600;
            color: var(--text-dim);
            white-space: nowrap;
            opacity: 0.5;
            transition: opacity 0.2s;
        }
        .logo-item:hover { opacity: 1; }
        .logo-item i { font-size: 1.1rem; }

        /* ─── STAT COUNTERS ─── */
        .stat-belt {
            padding: 60px 24px;
            background: radial-gradient(ellipse 80% 60% at 50% 0%, rgba(0,224,122,0.05), transparent);
            border-bottom: 1px solid var(--border3);
        }
        .stat-inner {
            max-width: 1200px;
            margin: 0 auto;
            text-align: center;
        }
        .stat-eyebrow {
            font-family: var(--mono);
            font-size: 0.7rem;
            color: var(--green);
            letter-spacing: 0.12em;
            text-transform: uppercase;
            margin-bottom: 20px;
        }
        .stat-headline {
            font-size: clamp(2rem, 4vw, 2.75rem);
            font-weight: 700;
            color: var(--text);
            letter-spacing: -0.03em;
            margin-bottom: 40px;
            line-height: 1.15;
        }
        .stat-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 2px;
        }
        @media (max-width: 768px) {
            .stat-grid { grid-template-columns: repeat(2, 1fr); gap: 2px; }
        }
        .stat-block {
            padding: 40px 20px;
            border: 1px solid var(--border3);
            text-align: left;
            position: relative;
            overflow: hidden;
        }
        .stat-block::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 1px;
            background: linear-gradient(90deg, transparent, var(--green), transparent);
            opacity: 0;
            transition: opacity 0.4s;
        }
        .stat-block:hover::before { opacity: 1; }
        .stat-num {
            font-family: var(--mono);
            font-size: 3.5rem;
            font-weight: 700;
            color: var(--text);
            line-height: 1;
            margin-bottom: 8px;
            letter-spacing: -0.04em;
        }
        .stat-num span { color: var(--green); }
        .stat-label {
            font-size: 0.9rem;
            color: var(--text-mid);
            line-height: 1.5;
        }
        .stat-sub {
            font-family: var(--mono);
            font-size: 0.7rem;
            color: var(--text-dim);
            margin-top: 8px;
        }

        /* ─── FEATURE MARQUEE ─── */
        .marquee-section {
            padding: 40px 0;
            overflow: hidden;
            border-bottom: 1px solid var(--border3);
            position: relative;
        }
        .marquee-label {
            text-align: center;
            font-family: var(--mono);
            font-size: 0.7rem;
            color: var(--text-dim);
            letter-spacing: 0.1em;
            text-transform: uppercase;
            margin-bottom: 40px;
        }
        .marquee-track {
            display: flex;
            gap: 16px;
            animation: marquee-scroll 35s linear infinite;
            width: max-content;
        }
        .marquee-track.reverse {
            animation: marquee-scroll-rev 35s linear infinite;
            margin-top: 16px;
        }
        @keyframes marquee-scroll {
            from { transform: translateX(0); }
            to   { transform: translateX(-50%); }
        }
        @keyframes marquee-scroll-rev {
            from { transform: translateX(-50%); }
            to   { transform: translateX(0); }
        }
        .marquee-chip {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 12px 20px;
            border: 1px solid var(--border3);
            border-radius: 8px;
            background: rgba(255,255,255,0.02);
            font-size: 0.875rem;
            color: var(--text);
            white-space: nowrap;
            transition: border-color 0.2s;
        }
        .marquee-chip:hover { border-color: rgba(255,255,255,0.15); }
        .marquee-chip i { font-size: 0.9rem; }
        .marquee-chip .mc-green { color: var(--green); }
        .marquee-chip .mc-blue  { color: var(--blue); }
        .marquee-chip .mc-amber { color: var(--amber); }
        .marquee-chip .mc-purple { color: var(--purple); }

        /* ─── PAYROLL PREVIEW MOCKUP ─── */
        .payroll-mockup-wrap {
            max-width: 1100px;
            margin: 0 auto;
            background: #0a0c16;
            border: 1px solid var(--border2);
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 40px 80px rgba(0,0,0,0.6);
        }
        .payroll-topbar {
            background: #0f1220;
            border-bottom: 1px solid var(--border3);
            padding: 14px 24px;
            display: flex;
            align-items: center;
            gap: 20px;
            font-family: var(--mono);
            font-size: 0.75rem;
        }
        .payroll-tabs {
            display: flex;
            gap: 2px;
        }
        .payroll-tab {
            padding: 6px 14px;
            border-radius: 6px 6px 0 0;
            font-family: var(--mono);
            font-size: 0.7rem;
            color: var(--text-dim);
            cursor: pointer;
            border: 1px solid transparent;
            border-bottom: none;
        }
        .payroll-tab.active {
            background: #0a0c16;
            color: var(--text);
            border-color: var(--border2);
        }
        .payroll-body {
            display: grid;
            grid-template-columns: 220px 1fr;
        }
        .payroll-sidebar {
            border-right: 1px solid var(--border3);
            padding: 20px 0;
        }
        .payroll-sidebar-item {
            padding: 10px 20px;
            font-size: 0.8rem;
            color: var(--text-dim);
            display: flex;
            align-items: center;
            gap: 10px;
            cursor: pointer;
        }
        .payroll-sidebar-item.active {
            background: rgba(0,224,122,0.06);
            color: var(--text);
            border-left: 2px solid var(--green);
        }
        .payroll-main {
            padding: 24px;
            text-align: left;
        }
        .payroll-row-head {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr 1fr 1fr;
            gap: 16px;
            padding: 8px 0;
            border-bottom: 1px solid var(--border3);
            font-family: var(--mono);
            font-size: 0.65rem;
            color: var(--text-dim);
            text-transform: uppercase;
            letter-spacing: 0.06em;
        }
        .payroll-row {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr 1fr 1fr;
            gap: 16px;
            padding: 14px 0;
            border-bottom: 1px solid rgba(255,255,255,0.03);
            font-size: 0.82rem;
            color: var(--text);
            align-items: center;
        }
        .payroll-row:hover { background: rgba(255,255,255,0.01); }
        .payroll-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 3px 8px;
            border-radius: 4px;
            font-family: var(--mono);
            font-size: 0.65rem;
            font-weight: 700;
        }
        .payroll-badge.paid {
            background: rgba(0,224,122,0.1);
            color: var(--green);
            border: 1px solid rgba(0,224,122,0.2);
        }
        .payroll-badge.pending {
            background: rgba(245,166,35,0.1);
            color: var(--amber);
            border: 1px solid rgba(245,166,35,0.2);
        }
        .payroll-badge.draft {
            background: rgba(255,255,255,0.05);
            color: var(--text-dim);
            border: 1px solid var(--border2);
        }
        .payroll-stat-bar {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            border-bottom: 1px solid var(--border3);
            margin-bottom: 20px;
        }
        .payroll-stat {
            padding: 16px 20px;
            border-right: 1px solid var(--border3);
        }
        .payroll-stat:last-child { border-right: none; }
        .payroll-stat-key {
            font-family: var(--mono);
            font-size: 0.65rem;
            color: var(--text-dim);
            text-transform: uppercase;
            letter-spacing: 0.06em;
            margin-bottom: 6px;
        }
        .payroll-stat-val {
            font-family: var(--mono);
            font-size: 1.4rem;
            font-weight: 700;
            color: var(--text);
        }
        .payroll-stat-val.green { color: var(--green); }

        /* ─── TESTIMONIALS ─── */
        .testimonials-section {
            padding: 120px 24px;
            border-top: 1px solid var(--border3);
        }
        .testimonials-inner {
            max-width: 1200px;
            margin: 0 auto;
            text-align: center;
        }
        .testimonials-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin-top: 60px;
            text-align: left;
        }
        @media (max-width: 900px) {
            .testimonials-grid { grid-template-columns: 1fr; }
        }
        .testimonial-card {
            background: rgba(255,255,255,0.02);
            border: 1px solid var(--border3);
            border-radius: 12px;
            padding: 28px;
            transition: border-color 0.2s, transform 0.2s;
        }
        .testimonial-card:hover {
            border-color: rgba(255,255,255,0.1);
            transform: translateY(-3px);
        }
        .testimonial-stars {
            display: flex;
            gap: 4px;
            margin-bottom: 16px;
        }
        .testimonial-stars i {
            color: var(--amber);
            font-size: 0.85rem;
        }
        .testimonial-quote {
            font-size: 0.95rem;
            color: var(--text);
            line-height: 1.7;
            margin-bottom: 20px;
        }
        .testimonial-author {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .testimonial-avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.75rem;
            font-weight: 700;
            font-family: var(--mono);
            flex-shrink: 0;
        }
        .testimonial-name {
            font-size: 0.875rem;
            font-weight: 600;
            color: var(--text);
        }
        .testimonial-role {
            font-size: 0.75rem;
            color: var(--text-dim);
            font-family: var(--mono);
        }

        /* ─── SECURITY TRUST SECTION ─── */
        .trust-section {
            padding: 100px 24px;
            border-top: 1px solid var(--border3);
            background: rgba(0,0,0,0.15);
        }
        .trust-inner {
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            flex-direction: column;
            gap: 60px;
            align-items: center;
        }
        .trust-header {
            text-align: center;
            max-width: 800px;
        }
        .trust-shield {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 16px;
            width: 100%;
        }
        @media (max-width: 900px) {
            .trust-shield { grid-template-columns: repeat(2, 1fr); }
        }
        @media (max-width: 600px) {
            .trust-shield { grid-template-columns: 1fr; }
        }
        .trust-badge {
            background: rgba(255,255,255,0.02);
            border: 1px solid var(--border3);
            border-radius: 12px;
            padding: 20px;
            display: flex;
            flex-direction: column;
            gap: 10px;
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            position: relative;
            overflow: hidden;
            z-index: 1;
        }
        .trust-badge::before {
            content: '';
            position: absolute;
            top: 0; left: -100%;
            width: 50%; height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.08), transparent);
            transform: skewX(-20deg);
            transition: all 0.6s ease-in-out;
            z-index: -1;
        }
        .trust-badge:hover { 
            border-color: rgba(0,224,122,0.5); 
            transform: translateY(-5px) scale(1.02);
            box-shadow: 0 12px 30px rgba(0,224,122,0.15);
            background: linear-gradient(145deg, rgba(255,255,255,0.04) 0%, rgba(0,224,122,0.05) 100%);
        }
        .trust-badge:hover::before {
            left: 200%;
        }
        .trust-badge i {
            font-size: 1.5rem;
            color: var(--green);
            transition: transform 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }
        .trust-badge:hover i {
            transform: scale(1.2) translateY(-2px);
        }
        .trust-badge h4 {
            font-size: 0.875rem;
            font-weight: 600;
            color: var(--text);
        }
        .trust-badge p {
            font-size: 0.75rem;
            color: var(--text-dim);
            line-height: 1.4;
        }

        /* ─── CURSOR-STYLE DEMO WINDOW ─── */
        .demo-section {
            padding: 60px 24px;
            border-top: 1px solid var(--border3);
            text-align: center;
        }
        .demo-section-inner {
            max-width: 1200px;
            margin: 0 auto;
        }
        .demo-window {
            margin-top: 60px;
            background: #0d0f1a;
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 14px;
            overflow: hidden;
            box-shadow:
                0 0 0 1px rgba(255,255,255,0.04),
                0 60px 120px rgba(0,0,0,0.7),
                0 0 80px rgba(0,224,122,0.04);
            text-align: left;
        }
        .live-app-iframe {
            width: 100%;
            height: 560px;
            border: 0;
            display: block;
            background: var(--bg);
        }
        @media (max-width: 900px) {
            .live-app-iframe { height: 480px; }
        }
        @media (max-width: 480px) {
            .live-app-iframe { height: 380px; }
        }
        .demo-titlebar {
            background: #161827;
            border-bottom: 1px solid rgba(255,255,255,0.06);
            padding: 13px 18px;
            display: flex;
            align-items: center;
            gap: 14px;
        }
        .demo-dots {
            display: flex;
            gap: 6px;
        }
        .demo-dot {
            width: 11px;
            height: 11px;
            border-radius: 50%;
        }
        .demo-dot.r { background: #ff5f57; }
        .demo-dot.y { background: #febc2e; }
        .demo-dot.g { background: #28c840; }
        .demo-title-text {
            font-family: var(--mono);
            font-size: 0.75rem;
            color: var(--text-dim);
            margin: 0 auto;
        }
        .demo-panels {
            display: grid;
            grid-template-columns: 220px 1fr 1fr;
            height: 460px;
        }
        @media (max-width: 900px) {
            .demo-panels { grid-template-columns: 1fr; height: auto; }
        }

        /* LEFT SIDEBAR */
        .demo-sidebar {
            border-right: 1px solid rgba(255,255,255,0.05);
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }
        .demo-sidebar-header {
            padding: 12px 14px 8px;
            font-family: var(--mono);
            font-size: 0.6rem;
            color: var(--text-dim);
            letter-spacing: 0.1em;
            text-transform: uppercase;
            border-bottom: 1px solid rgba(255,255,255,0.04);
        }
        .demo-sidebar-item {
            padding: 10px 14px;
            border-bottom: 1px solid rgba(255,255,255,0.03);
            cursor: pointer;
            transition: background 0.2s;
        }
        .demo-sidebar-item:hover { background: rgba(255,255,255,0.02); }
        .demo-sidebar-item.active { background: rgba(0,224,122,0.05); }
        .demo-sidebar-item-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 3px;
        }
        .demo-sidebar-name {
            font-size: 0.78rem;
            font-weight: 600;
            color: var(--text);
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .demo-sidebar-name .si {
            width: 14px;
            height: 14px;
            border-radius: 50%;
            border: 1.5px solid var(--green);
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        .demo-sidebar-name .si.done { background: var(--green); border-color: var(--green); }
        .demo-sidebar-name .si.pending { background: transparent; border-color: var(--amber); }
        .demo-sidebar-name .si.done::after {
            content: '';
            display: block;
            width: 5px;
            height: 3px;
            border-left: 1.5px solid #000;
            border-bottom: 1.5px solid #000;
            transform: rotate(-45deg) translate(0.5px, -0.5px);
        }
        .demo-sidebar-time {
            font-family: var(--mono);
            font-size: 0.6rem;
            color: var(--text-dim);
        }
        .demo-sidebar-sub {
            font-size: 0.7rem;
            color: var(--text-dim);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .demo-sidebar-diff {
            font-family: var(--mono);
            font-size: 0.6rem;
            margin-top: 2px;
        }
        .demo-sidebar-diff .ins { color: var(--green); }
        .demo-sidebar-diff .del { color: #ff4a4a; }

        /* CENTER PANEL */
        .demo-center {
            border-right: 1px solid rgba(255,255,255,0.05);
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }
        .demo-center-header {
            padding: 12px 16px;
            border-bottom: 1px solid rgba(255,255,255,0.04);
            font-size: 0.82rem;
            font-weight: 600;
            color: var(--text);
        }
        .demo-center-body {
            flex: 1;
            padding: 16px;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        .demo-prompt-box {
            background: rgba(255,255,255,0.03);
            border: 1px solid rgba(255,255,255,0.07);
            border-radius: 8px;
            padding: 10px 14px;
            font-size: 0.8rem;
            color: var(--text);
            line-height: 1.5;
        }
        .demo-log-line {
            font-family: var(--mono);
            font-size: 0.72rem;
            color: var(--text-mid);
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 2px 0;
        }
        .demo-log-line .dl-action { color: var(--text-dim); }
        .demo-log-line .dl-file { color: var(--blue); }
        .demo-log-line .dl-ok { color: var(--green); }
        .demo-log-line .dl-warn { color: var(--amber); }
        .demo-log-line .dl-think { color: var(--purple); font-style: italic; }
        .demo-file-change {
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: rgba(255,255,255,0.025);
            border: 1px solid rgba(255,255,255,0.06);
            border-radius: 6px;
            padding: 8px 12px;
            font-family: var(--mono);
            font-size: 0.7rem;
        }
        .demo-file-change .fc-name { color: var(--text); }
        .demo-file-change .fc-ins { color: var(--green); }
        .demo-file-change .fc-del { color: #ff4a4a; }
        .demo-center-footer {
            padding: 10px 16px;
            border-top: 1px solid rgba(255,255,255,0.04);
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .demo-center-input {
            flex: 1;
            background: rgba(255,255,255,0.03);
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 6px;
            padding: 8px 12px;
            font-family: var(--mono);
            font-size: 0.72rem;
            color: var(--text-dim);
        }
        .demo-pill {
            background: rgba(255,255,255,0.05);
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 999px;
            padding: 4px 10px;
            font-family: var(--mono);
            font-size: 0.65rem;
            color: var(--text-dim);
            display: flex;
            align-items: center;
            gap: 5px;
        }

        /* RIGHT PANEL — PREVIEW */
        .demo-preview {
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }
        .demo-preview-bar {
            background: #1a1d2e;
            border-bottom: 1px solid rgba(255,255,255,0.05);
            padding: 8px 14px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .demo-nav-btn {
            width: 18px;
            height: 18px;
            border-radius: 4px;
            background: rgba(255,255,255,0.06);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--text-dim);
            font-size: 0.6rem;
        }
        .demo-address {
            flex: 1;
            background: rgba(255,255,255,0.04);
            border: 1px solid rgba(255,255,255,0.06);
            border-radius: 5px;
            padding: 4px 10px;
            font-family: var(--mono);
            font-size: 0.65rem;
            color: var(--text-dim);
        }
        .demo-preview-body {
            flex: 1;
            padding: 20px;
            overflow: hidden;
            background: #08090f;
        }
        /* Payslip preview inside the right pane */
        .payslip-preview {
            background: #0d0f1a;
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 10px;
            padding: 20px;
            font-family: var(--mono);
        }
        .payslip-preview-head {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 16px;
            padding-bottom: 12px;
            border-bottom: 1px solid rgba(255,255,255,0.06);
        }
        .payslip-preview-company {
            font-size: 0.75rem;
            font-weight: 700;
            color: var(--text);
        }
        .payslip-preview-label {
            font-size: 0.6rem;
            color: var(--green);
            background: rgba(0,224,122,0.08);
            border: 1px solid rgba(0,224,122,0.15);
            padding: 2px 8px;
            border-radius: 4px;
        }
        .payslip-row {
            display: flex;
            justify-content: space-between;
            padding: 5px 0;
            font-size: 0.68rem;
            border-bottom: 1px solid rgba(255,255,255,0.03);
        }
        .payslip-row .pr-key { color: var(--text-dim); }
        .payslip-row .pr-val { color: var(--text); font-weight: 600; }
        .payslip-row .pr-val.green { color: var(--green); }
        .payslip-row .pr-val.red { color: #ff4a4a; }
        .payslip-total {
            display: flex;
            justify-content: space-between;
            padding: 10px 0 0;
            font-size: 0.8rem;
            font-weight: 700;
            margin-top: 4px;
        }
        .payslip-total .pt-key { color: var(--text-mid); }
        .payslip-total .pt-val { color: var(--green); font-size: 1rem; }

        /* Blinking cursor animation in log */
        @keyframes demo-blink {
            0%, 100% { opacity: 1; }
            50% { opacity: 0; }
        }
        .demo-cursor {
            display: inline-block;
            width: 6px;
            height: 12px;
            background: var(--green);
            vertical-align: middle;
            animation: demo-blink 1s step-start infinite;
            margin-left: 2px;
            border-radius: 1px;
        }
        /* Animated log lines fade in */
        @keyframes log-in {
            from { opacity: 0; transform: translateY(4px); }
            to   { opacity: 1; transform: translateY(0); }
        }
        .demo-log-line { animation: log-in 0.3s ease both; }
        .demo-log-line:nth-child(1) { animation-delay: 0.1s; }
        .demo-log-line:nth-child(2) { animation-delay: 0.5s; }
        .demo-log-line:nth-child(3) { animation-delay: 0.9s; }
        .demo-log-line:nth-child(4) { animation-delay: 1.3s; }
        .demo-log-line:nth-child(5) { animation-delay: 1.7s; }
        .demo-file-change:nth-child(1) { animation: log-in 0.3s ease 2.2s both; }
        .demo-file-change:nth-child(2) { animation: log-in 0.3s ease 2.6s both; }
        .demo-done-msg { animation: log-in 0.4s ease 3.1s both; }

        /* ─── BIG TYPE CTA ─── */
        .bigtype-cta {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            justify-content: center;
            padding: 100px 48px 80px;
            position: relative;
            overflow: hidden;
            border-top: 1px solid var(--border3);
        }
        .bigtype-cta::before {
            content: '';
            position: absolute;
            inset: 0;
            background: radial-gradient(ellipse 70% 60% at 80% 50%, rgba(0,224,122,0.04) 0%, transparent 70%);
            pointer-events: none;
        }
        .bigtype-inner {
            position: relative;
            z-index: 1;
            max-width: 1400px;
            width: 100%;
            margin: 0 auto;
        }
        .bigtype-overline {
            font-family: var(--mono);
            font-size: 0.72rem;
            letter-spacing: 0.18em;
            color: var(--text-dim);
            text-transform: uppercase;
            margin-bottom: 48px;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .bigtype-overline::before {
            content: '';
            display: inline-block;
            width: 28px;
            height: 1px;
            background: var(--text-dim);
        }
        .bigtype-headline {
            font-size: clamp(4rem, 12vw, 11rem);
            font-weight: 800;
            line-height: 0.92;
            letter-spacing: -0.04em;
            color: #fff;
            margin-bottom: 0;
            font-family: var(--sans);
        }
        @keyframes ops-color-cycle {
            0%   { color: #00e07a; }   /* green */
            20%  { color: #00c8f0; }   /* cyan */
            40%  { color: #a78bfa; }   /* violet */
            60%  { color: #f472b6; }   /* pink */
            80%  { color: #fbbf24; }   /* amber */
            100% { color: #00e07a; }   /* back to green */
        }
        .bigtype-headline .bt-accent {
            display: block;
            animation: ops-color-cycle 5s ease-in-out infinite;
        }
        .bigtype-sub {
            margin-top: 60px;
            font-family: var(--mono);
            font-size: 0.78rem;
            letter-spacing: 0.22em;
            color: var(--text-dim);
            text-transform: uppercase;
        }
        .bigtype-sub span {
            color: var(--text-mid);
        }
        .bigtype-actions {
            margin-top: 56px;
            display: flex;
            gap: 16px;
            align-items: center;
            flex-wrap: wrap;
        }
        .bigtype-scroll-hint {
            position: absolute;
            bottom: 40px;
            right: 48px;
            font-family: var(--mono);
            font-size: 0.65rem;
            letter-spacing: 0.15em;
            color: var(--text-dim);
            text-transform: uppercase;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .bigtype-scroll-hint::after {
            content: '';
            display: block;
            width: 1px;
            height: 40px;
            background: var(--text-dim);
            opacity: 0.4;
        }
        @media (max-width: 768px) {
            .bigtype-cta { padding: 80px 24px 60px; }
            .bigtype-scroll-hint { display: none; }
        }

        /* ─── PLAN MODAL ─── */
        .plan-modal-overlay {
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.75);
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
            z-index: 9999;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
            opacity: 0;
            visibility: hidden;
            transition: opacity 0.25s ease, visibility 0.25s ease;
        }
        .plan-modal-overlay.open {
            opacity: 1;
            visibility: visible;
        }
        .plan-modal {
            background: #0d0f1a;
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 18px;
            width: 100%;
            max-width: 860px;
            max-height: 90vh;
            overflow-y: auto;
            padding: 40px;
            position: relative;
            box-shadow: 0 40px 100px rgba(0,0,0,0.7), 0 0 0 1px rgba(255,255,255,0.04);
            transform: translateY(12px);
            transition: transform 0.28s cubic-bezier(0.34, 1.56, 0.64, 1);
        }
        .plan-modal-overlay.open .plan-modal {
            transform: translateY(0);
        }
        .plan-modal-close {
            position: absolute;
            top: 20px;
            right: 20px;
            width: 32px;
            height: 32px;
            border-radius: 8px;
            background: rgba(255,255,255,0.06);
            border: 1px solid rgba(255,255,255,0.08);
            color: var(--text-dim);
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-size: 1rem;
            line-height: 1;
            transition: background 0.2s, color 0.2s;
        }
        .plan-modal-close:hover { background: rgba(255,255,255,0.1); color: var(--text); }
        .plan-modal-title {
            font-size: 1.4rem;
            font-weight: 700;
            color: var(--text);
            margin-bottom: 6px;
            letter-spacing: -0.02em;
        }
        .plan-modal-sub {
            font-family: var(--mono);
            font-size: 0.7rem;
            color: var(--text-dim);
            letter-spacing: 0.1em;
            text-transform: uppercase;
            margin-bottom: 36px;
        }
        .plan-modal-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        @media (max-width: 640px) {
            .plan-modal-grid { grid-template-columns: 1fr; }
            .plan-modal { padding: 28px 20px; }
        }
        .plan-card {
            background: rgba(255,255,255,0.02);
            border: 1px solid rgba(255,255,255,0.07);
            border-radius: 12px;
            padding: 28px;
            display: flex;
            flex-direction: column;
            gap: 0;
            transition: border-color 0.2s;
        }
        .plan-card:hover { border-color: rgba(0,224,122,0.2); }
        .plan-card-label {
            font-family: var(--mono);
            font-size: 0.62rem;
            letter-spacing: 0.1em;
            text-transform: uppercase;
            padding: 4px 10px;
            border-radius: 4px;
            margin-bottom: 20px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            width: fit-content;
        }
        .plan-card-label.solo {
            background: rgba(0,224,122,0.07);
            color: var(--green);
            border: 1px solid rgba(0,224,122,0.18);
        }
        .plan-card-label.beta {
            background: rgba(251,191,36,0.07);
            color: var(--amber);
            border: 1px solid rgba(251,191,36,0.18);
        }
        .plan-card-h {
            font-size: 1.3rem;
            font-weight: 700;
            color: var(--text);
            line-height: 1.2;
            margin-bottom: 10px;
            letter-spacing: -0.02em;
        }
        .plan-card-p {
            font-size: 0.8rem;
            color: var(--text-dim);
            line-height: 1.6;
            margin-bottom: 20px;
        }
        .plan-card-perks {
            list-style: none;
            margin: 0 0 24px;
            padding: 0;
            display: flex;
            flex-direction: column;
            gap: 9px;
            flex: 1;
        }
        .plan-card-perks li {
            font-size: 0.78rem;
            color: var(--text-mid);
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .plan-card-perks li::before {
            content: '';
            display: inline-block;
            width: 14px;
            height: 14px;
            border-radius: 50%;
            border: 1.5px solid var(--green);
            background: var(--green);
            flex-shrink: 0;
            position: relative;
        }
        .plan-card-price {
            font-size: 2rem;
            font-weight: 800;
            color: var(--text);
            letter-spacing: -0.04em;
            margin-bottom: 2px;
        }
        .plan-card-price-sub {
            font-family: var(--mono);
            font-size: 0.62rem;
            color: var(--text-dim);
            letter-spacing: 0.08em;
            text-transform: uppercase;
            margin-bottom: 20px;
        }
        .plan-modal-footer {
            margin-top: 24px;
            text-align: center;
            font-family: var(--mono);
            font-size: 0.68rem;
            color: var(--text-dim);
        }
        .plan-modal-footer a { color: var(--text-mid); text-decoration: underline; text-underline-offset: 3px; }
    </style>
</head>
<body>
    <div class="global-bg"></div>

<div class="app-wrapper">
<!-- NAV -->
<nav>
    <?= renderLogo('navbar') ?>
    <div class="nav-links">
        <a href="#whats-inside">Platform</a>
        <a href="deep_dive.php">Deep Dive</a>
        <a href="design.php">Design</a>
        <a href="#why">Why Us</a>
        <a href="#story">The Story</a>
        <a href="#beta">Beta</a>
        <?php if ($loggedIn): ?>
            <a href="<?= url('/frontend/dist/index.html?v=<?= time() ?>#/dashboard') ?>" class="nav-cta">[ RESUME ]</a>
        <?php else: ?>
            <a href="<?= url('/login.php') ?>" class="nav-cta">[ LOGIN ]</a>
        <?php endif; ?>
    </div>
</nav>

<!-- HERO -->
<section class="hero" id="home">
    <div class="hero-glow-mid"></div>
    <div class="hero-inner">
        <div class="hero-status">
            <span class="hero-status-dot"></span>
            Disponible — active engines online
        </div>

        <h1 class="hero-h1-new">
            Your team deserves<br>
            <span class="gradient-text">more than a spreadsheet.</span><span class="cursor-blink">_</span>
        </h1>

        <div class="hero-bottom-layout">
            <p class="hero-sub-new">
                Respawn Logics is the all-in-one intelligent platform for managing HR, Payroll, ATS, and Employee Services. Connect your directory, define your rules, and run organization infrastructure with zero friction.
            </p>

            <div class="hero-actions-new">
                <?php if ($loggedIn): ?>
                    <a href="<?= url('/frontend/dist/index.html?v=' . time() . '#/dashboard') ?>" class="btn-neon-pill">
                        Resume Session <i data-lucide="arrow-right"></i>
                    </a>
                <?php else: ?>
                    <a href="<?= url('/frontend/dist/index.html?v=' . time() . '#/setup') ?>" class="btn-neon-pill">
                        Get Started Free <i data-lucide="arrow-right"></i>
                    </a>
                    <a href="<?= url('/login.php') ?>" class="btn-outline-pill">
                        View Demo
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="hero-scroll-indicator">
        scroll
        <div class="hero-scroll-line"></div>
    </div>
</section>

<!-- SOCIAL PROOF LOGO BAR -->
<div class="logo-bar">
    <div class="logo-track" id="logo-track">
        <div class="logo-item"><i data-lucide="briefcase"></i> Acme Corp</div>
        <div class="logo-item"><i data-lucide="building-2"></i> NovaTech PH</div>
        <div class="logo-item"><i data-lucide="landmark"></i> Pillar Financial</div>
        <div class="logo-item"><i data-lucide="zap"></i> SpeedOps Inc</div>
        <div class="logo-item"><i data-lucide="globe"></i> GlobalHire Corp</div>
        <div class="logo-item"><i data-lucide="cpu"></i> StackBase Ltd</div>
        <div class="logo-item"><i data-lucide="bar-chart-2"></i> Meridian Analytics</div>
        <div class="logo-item"><i data-lucide="layers"></i> LayerHR</div>
        <!-- Duplicate for seamless scroll -->
        <div class="logo-item"><i data-lucide="briefcase"></i> Acme Corp</div>
        <div class="logo-item"><i data-lucide="building-2"></i> NovaTech PH</div>
        <div class="logo-item"><i data-lucide="landmark"></i> Pillar Financial</div>
        <div class="logo-item"><i data-lucide="zap"></i> SpeedOps Inc</div>
        <div class="logo-item"><i data-lucide="globe"></i> GlobalHire Corp</div>
        <div class="logo-item"><i data-lucide="cpu"></i> StackBase Ltd</div>
        <div class="logo-item"><i data-lucide="bar-chart-2"></i> Meridian Analytics</div>
        <div class="logo-item"><i data-lucide="layers"></i> LayerHR</div>
    </div>
</div>

<!-- ANIMATED STAT COUNTERS -->
<div class="trust-section" id="whats-inside" style="background: radial-gradient(ellipse 80% 60% at 50% 0%, rgba(0,224,122,0.05), transparent);">
    <div class="trust-inner">
        <div class="trust-header" style="transform: translateZ(0); will-change: transform; position: relative; z-index: 2;">
            <div style="font-family: var(--mono); font-size: 0.72rem; color: var(--green); letter-spacing: 0.14em; text-transform: uppercase; margin-bottom: 16px;">// WHAT'S INSIDE</div>
            <h2 style="font-size: clamp(2rem, 4vw, 2.75rem); font-family: var(--sans); font-weight: 700; line-height: 1.15; color: #fff;">One platform. Every HR tool your team needs.</h2>
            <p style="color: var(--text-mid); font-size: 1.05rem; line-height: 1.6; margin-top: 14px;">No more juggling separate systems for payroll, hiring, and attendance — it's all built in and works together.</p>
        </div>
        <div class="trust-shield">
            <div class="trust-badge">
                <i data-lucide="calculator"></i>
                <h4>Payroll &amp; Statutory</h4>
                <p>SSS, PhilHealth, Pag-IBIG, BIR withholding, and 13th-month pay — computed correctly on every run.</p>
            </div>
            <div class="trust-badge">
                <i data-lucide="clock"></i>
                <h4>Attendance &amp; Time</h4>
                <p>Hours, overtime, night differential, and holidays tracked and fed straight into payroll.</p>
            </div>
            <div class="trust-badge">
                <i data-lucide="kanban"></i>
                <h4>Hiring (ATS)</h4>
                <p>Post jobs, move applicants through each stage, and turn a hire into an employee in one click.</p>
            </div>
            <div class="trust-badge">
                <i data-lucide="folder-open"></i>
                <h4>Employee Directory &amp; 201 Files</h4>
                <p>A complete, secure record for every employee — contracts, IDs, and history in one place.</p>
            </div>
            <div class="trust-badge">
                <i data-lucide="calendar-check"></i>
                <h4>Leave Management</h4>
                <p>Requests, approvals, and balances handled automatically, with your leave policies built in.</p>
            </div>
            <div class="trust-badge">
                <i data-lucide="trending-up"></i>
                <h4>Performance Reviews</h4>
                <p>Set goals, run review cycles, and keep a clear history of every evaluation.</p>
            </div>
            <div class="trust-badge">
                <i data-lucide="scale"></i>
                <h4>Employee Relations</h4>
                <p>Log cases, track resolutions, and keep a documented, compliant paper trail.</p>
            </div>
            <div class="trust-badge">
                <i data-lucide="life-buoy"></i>
                <h4>Service Desk</h4>
                <p>Employees get HR answers fast; complex cases route straight to your team.</p>
            </div>
        </div>
    </div>
</div>

<!-- FEATURE MARQUEE -->
<div class="marquee-section">
    <div class="marquee-label">// EVERYTHING INCLUDED OUT OF THE BOX</div>
    <div style="overflow:hidden;">
        <div class="marquee-track">
            <div class="marquee-chip"><i class="mc-green" data-lucide="users"></i> Employee Directory</div>
            <div class="marquee-chip"><i class="mc-blue" data-lucide="calculator"></i> Statutory Payroll</div>
            <div class="marquee-chip"><i class="mc-amber" data-lucide="file-text"></i> Contract Manager</div>
            <div class="marquee-chip"><i class="mc-purple" data-lucide="kanban"></i> ATS Pipeline</div>
            <div class="marquee-chip"><i class="mc-green" data-lucide="clock"></i> Attendance Engine</div>
            <div class="marquee-chip"><i class="mc-blue" data-lucide="message-square"></i> Service Desk</div>
            <div class="marquee-chip"><i class="mc-amber" data-lucide="shield-check"></i> RBAC Security</div>
            <div class="marquee-chip"><i class="mc-purple" data-lucide="bar-chart"></i> HR Analytics</div>
            <div class="marquee-chip"><i class="mc-green" data-lucide="leaf"></i> Leave Management</div>
            <div class="marquee-chip"><i class="mc-blue" data-lucide="file-badge"></i> 201 Files</div>
            <div class="marquee-chip"><i class="mc-amber" data-lucide="landmark"></i> BIR Compliance</div>
            <div class="marquee-chip"><i class="mc-purple" data-lucide="bot"></i> AI Companion</div>
            <!-- Duplicate -->
            <div class="marquee-chip"><i class="mc-green" data-lucide="users"></i> Employee Directory</div>
            <div class="marquee-chip"><i class="mc-blue" data-lucide="calculator"></i> Statutory Payroll</div>
            <div class="marquee-chip"><i class="mc-amber" data-lucide="file-text"></i> Contract Manager</div>
            <div class="marquee-chip"><i class="mc-purple" data-lucide="kanban"></i> ATS Pipeline</div>
            <div class="marquee-chip"><i class="mc-green" data-lucide="clock"></i> Attendance Engine</div>
            <div class="marquee-chip"><i class="mc-blue" data-lucide="message-square"></i> Service Desk</div>
            <div class="marquee-chip"><i class="mc-amber" data-lucide="shield-check"></i> RBAC Security</div>
            <div class="marquee-chip"><i class="mc-purple" data-lucide="bar-chart"></i> HR Analytics</div>
            <div class="marquee-chip"><i class="mc-green" data-lucide="leaf"></i> Leave Management</div>
            <div class="marquee-chip"><i class="mc-blue" data-lucide="file-badge"></i> 201 Files</div>
            <div class="marquee-chip"><i class="mc-amber" data-lucide="landmark"></i> BIR Compliance</div>
            <div class="marquee-chip"><i class="mc-purple" data-lucide="bot"></i> AI Companion</div>
        </div>
        <div class="marquee-track reverse" style="margin-top: 16px;">
            <div class="marquee-chip"><i class="mc-blue" data-lucide="git-branch"></i> Audit Trail</div>
            <div class="marquee-chip"><i class="mc-green" data-lucide="upload"></i> Document Uploads</div>
            <div class="marquee-chip"><i class="mc-amber" data-lucide="receipt"></i> Payslip Generator</div>
            <div class="marquee-chip"><i class="mc-purple" data-lucide="network"></i> Org Chart</div>
            <div class="marquee-chip"><i class="mc-green" data-lucide="trending-up"></i> Performance Mgmt</div>
            <div class="marquee-chip"><i class="mc-blue" data-lucide="mail"></i> Email Notifications</div>
            <div class="marquee-chip"><i class="mc-amber" data-lucide="key"></i> SSO Support</div>
            <div class="marquee-chip"><i class="mc-purple" data-lucide="database"></i> Data Isolation</div>
            <div class="marquee-chip"><i class="mc-green" data-lucide="calendar"></i> Holiday Engine</div>
            <div class="marquee-chip"><i class="mc-blue" data-lucide="activity"></i> Health & Benefits</div>
            <div class="marquee-chip"><i class="mc-amber" data-lucide="award"></i> Offboarding</div>
            <div class="marquee-chip"><i class="mc-purple" data-lucide="code"></i> REST API</div>
            <!-- Duplicate -->
            <div class="marquee-chip"><i class="mc-blue" data-lucide="git-branch"></i> Audit Trail</div>
            <div class="marquee-chip"><i class="mc-green" data-lucide="upload"></i> Document Uploads</div>
            <div class="marquee-chip"><i class="mc-amber" data-lucide="receipt"></i> Payslip Generator</div>
            <div class="marquee-chip"><i class="mc-purple" data-lucide="network"></i> Org Chart</div>
            <div class="marquee-chip"><i class="mc-green" data-lucide="trending-up"></i> Performance Mgmt</div>
            <div class="marquee-chip"><i class="mc-blue" data-lucide="mail"></i> Email Notifications</div>
            <div class="marquee-chip"><i class="mc-amber" data-lucide="key"></i> SSO Support</div>
            <div class="marquee-chip"><i class="mc-purple" data-lucide="database"></i> Data Isolation</div>
            <div class="marquee-chip"><i class="mc-green" data-lucide="calendar"></i> Holiday Engine</div>
            <div class="marquee-chip"><i class="mc-blue" data-lucide="activity"></i> Health & Benefits</div>
            <div class="marquee-chip"><i class="mc-amber" data-lucide="award"></i> Offboarding</div>
            <div class="marquee-chip"><i class="mc-purple" data-lucide="code"></i> REST API</div>
        </div>
    </div>
</div>


<!-- APP PREVIEW WINDOW — placed above features -->
<div style="padding: 60px 5% 80px; background: var(--bg); position:relative; z-index:1;">
    <div style="max-width: 1240px; margin: 0 auto;">
        <!-- section label -->
        <p style="font-family:var(--mono);font-size:0.7rem;letter-spacing:0.14em;text-transform:uppercase;color:var(--text-dim);text-align:center;margin-bottom:20px;">// LIVE PLATFORM PREVIEW</p>
        <div style="background: #0d0f1a; border: 1px solid rgba(255,255,255,0.09); border-radius: 16px; overflow: hidden; box-shadow: 0 0 0 1px rgba(255,255,255,0.04), 0 60px 120px rgba(0,0,0,0.75), 0 0 80px rgba(0,224,122,0.05);">
            <!-- title bar -->
            <div style="background: #161827; border-bottom: 1px solid rgba(255,255,255,0.06); padding: 13px 20px; display: flex; align-items: center; gap: 14px;">
                <div style="display:flex; gap:7px;">
                    <div style="width:12px;height:12px;border-radius:50%;background:#ff5f57;"></div>
                    <div style="width:12px;height:12px;border-radius:50%;background:#febc2e;"></div>
                    <div style="width:12px;height:12px;border-radius:50%;background:#28c840;"></div>
                </div>
                <div style="font-family:var(--mono);font-size:0.75rem;color:var(--text-dim);margin:0 auto;">Respawn Logics — Operations Center</div>
            </div>
            <!-- iframe -->
            <iframe src="<?= url('/frontend/dist/index.html?demo=true#/dashboard') ?>"
                title="Respawn Logics — live demo"
                style="width:100%;height:640px;border:0;display:block;background:#0d0f1a;"></iframe>
        </div>

    </div>
</div>

<!-- TIMELINE SECTION 1: DIRECTORY ROUTING (RAILWAY TIMELINE ACCENT) -->
<section class="timeline-section" id="journey">
    <div class="timeline-line"></div>
    
    <div class="revamp-container">
        <div class="revamp-grid">
            <div>
                <span class="pill-label">Directory and Security</span>
                <h2 style="font-size: clamp(2rem, 4vw, 2.75rem); font-family: var(--sans); font-weight: 700; line-height: 1.15; margin-bottom: 16px;">Ready to use<br>from day one.</h2>
                <p style="color: var(--text-mid); font-size: 1.05rem; line-height: 1.6;">Roles, Philippine statutory tables (SSS, BIR, PhilHealth), and secure file storage are all set up the moment you create your workspace — nothing to configure.</p>

                <div class="revamp-list">
                    <div class="revamp-item">
                        <i data-lucide="shield"></i>
                        <div>
                            <h4>Your data stays your data</h4>
                            <p>Each company's information is fully separated and walled off — no one outside your organization can ever see it.</p>
                        </div>
                    </div>
                    <div class="revamp-item">
                        <i data-lucide="git-branch"></i>
                        <div>
                            <h4>A complete record of every change</h4>
                            <p>Every edit to an employee record, payroll calculation, and login is automatically logged — and can't be tampered with.</p>
                        </div>
                    </div>
                </div>

                <div class="alternatives-row">
                    <span>Alternative to</span>
                    <span style="font-weight: 600; color: var(--text); opacity: 0.6;">Workday</span>
                    <span style="font-weight: 600; color: var(--text); opacity: 0.6;">BambooHR</span>
                    <span style="font-weight: 600; color: var(--text); opacity: 0.6;">Rippling</span>
                </div>
            </div>

            <div>
                <!-- Floating canvas diagram showing active microservices -->
                <div class="railway-canvas">
                    <!-- auth service card -->
                    <div class="canvas-card" style="top: 30px; left: 40px;">
                        <div><span class="dot"></span><span style="font-weight:600; color:#fff;">auth.service</span></div>
                        <div class="card-host">auth-prod.respawn.internal</div>
                        <div class="card-status"><i data-lucide="check" size="12"></i> Online</div>
                    </div>
                    <!-- payroll engine card -->
                    <div class="canvas-card" style="top: 190px; right: 40px; border-color: rgba(79,142,247,0.3);">
                        <div><span class="dot" style="background:var(--blue); box-shadow:0 0 8px var(--blue);"></span><span style="font-weight:600; color:#fff;">payroll.engine</span></div>
                        <div class="card-host">payroll-ph.respawn.internal</div>
                        <div class="card-status" style="color:var(--blue);"><i data-lucide="activity" size="12"></i> Idle</div>
                    </div>
                    <!-- secure storage card -->
                    <div class="canvas-card" style="top: 110px; left: 160px; border-color: rgba(245,166,35,0.3);">
                        <div><span class="dot" style="background:var(--amber); box-shadow:0 0 8px var(--amber);"></span><span style="font-weight:600; color:#fff;">isolated.storage</span></div>
                        <div class="card-host">/var/data/isolated_storage</div>
                        <div class="card-status" style="color:var(--amber);"><i data-lucide="shield-alert" size="12"></i> Armed</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- TIMELINE SECTION 2: SCALING CALCULATIONS (RAILWAY ORANGE TIMELINE) -->
<section class="timeline-section" id="scale" style="background: rgba(0,0,0,0.15);">
    <div class="timeline-line orange"></div>
    
    <div class="revamp-container">
        <div class="revamp-grid reverse">
            <div>
                <!-- Floating backend runtimes -->
                <div class="railway-canvas" style="min-height: 320px;">
                    <div class="canvas-card" style="top: 30px; left: 50px; width: 220px; border-color: rgba(245,166,35,0.3);">
                        <div><span class="dot" style="background:var(--amber); box-shadow: 0 0 8px var(--amber);"></span><span style="font-weight:600; color:#fff;">calc-node-1</span></div>
                        <div class="card-status" style="color:var(--amber);"><i data-lucide="check" size="12"></i> Running SSS</div>
                    </div>
                    <div class="canvas-card" style="top: 120px; right: 50px; width: 220px; border-color: rgba(245,166,35,0.3);">
                        <div><span class="dot" style="background:var(--amber); box-shadow: 0 0 8px var(--amber);"></span><span style="font-weight:600; color:#fff;">calc-node-2</span></div>
                        <div class="card-status" style="color:var(--amber);"><i data-lucide="check" size="12"></i> Running BIR</div>
                    </div>
                    <div class="canvas-card" style="top: 210px; left: 80px; width: 220px; border-color: rgba(245,166,35,0.3);">
                        <div><span class="dot" style="background:var(--amber); box-shadow: 0 0 8px var(--amber);"></span><span style="font-weight:600; color:#fff;">calc-node-3</span></div>
                        <div class="card-status" style="color:var(--amber);"><i data-lucide="check" size="12"></i> Idle</div>
                    </div>
                </div>
            </div>

            <div>
                <span class="pill-label orange">Scale and Compute</span>
                <h2 style="font-size: clamp(2rem, 4vw, 2.75rem); font-family: var(--sans); font-weight: 700; line-height: 1.15; margin-bottom: 16px;">Payroll that stays accurate as you grow.</h2>
                <p style="color: var(--text-mid); font-size: 1.05rem; line-height: 1.6;">Process payroll, benefits, and statutory contributions for your whole team in one run — fast, whether you have 10 employees or 10,000.</p>

                <div class="revamp-list">
                    <div class="revamp-item orange">
                        <i data-lucide="cpu"></i>
                        <div>
                            <h4>Built to handle any headcount</h4>
                            <p>SSS, PhilHealth, Pag-IBIG, and tax are computed for every employee at once — no slowdowns as your team grows.</p>
                        </div>
                    </div>
                    <div class="revamp-item orange">
                        <i data-lucide="sliders"></i>
                        <div>
                            <h4>Rate changes are just a setting</h4>
                            <p>When SSS, PhilHealth, or tax rates change, you update a value — no waiting on a developer or a software patch.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- TIMELINE SECTION 3: OBSERVABILITY AUDIT TRAIL (RAILWAY OBS TIMELINE) -->
<section class="timeline-section" id="observability">
    <div class="timeline-line blue"></div>
    
    <div class="revamp-container">
        <div class="revamp-grid" style="grid-template-columns: 40% 60%;">
            <div>
                <span class="pill-label blue">Monitor and Observe</span>
                <h2 style="font-size: clamp(2rem, 4vw, 2.75rem); font-family: var(--sans); font-weight: 700; line-height: 1.15; margin-bottom: 16px;">Audits, metrics, and guards in one place.</h2>
                <p style="color: var(--text-mid); font-size: 1.05rem; line-height: 1.6;">See every payroll run, document access, and compliance check from a single live dashboard — so nothing important slips by unnoticed.</p>

                <div class="revamp-list">
                    <div class="revamp-item blue">
                        <i data-lucide="layout-grid"></i>
                        <div>
                            <h4>Everything in one dashboard</h4>
                            <p>Watch payroll, hiring, and compliance activity update in real time — no jumping between tools or spreadsheets.</p>
                        </div>
                    </div>
                    <div class="revamp-item blue">
                        <i data-lucide="bell"></i>
                        <div>
                            <h4>Alerts before problems become breaches</h4>
                            <p>Get notified the moment a file-access rule or setting looks wrong, so sensitive employee data stays protected.</p>
                        </div>
                    </div>
                </div>
            </div>

            <div>
                <!-- Observability Dashboard mockup -->
                <div class="obs-dashboard">
                    <div class="obs-header">
                        <div style="display:flex; align-items:center; gap:8px;">
                            <span style="width:10px; height:10px; border-radius:50%; background:#ef4444;"></span>
                            <span style="color:#fff; font-weight:600;">respawn-logics / environment</span>
                        </div>
                        <div>Production</div>
                    </div>
                    <div class="obs-body">
                        <div class="obs-logs">
                            <div class="log-row">
                                <span class="log-time">[14:15:30]</span>
                                <span class="log-tag">SYSTEM</span>
                                <span>Secure storage online · employee files isolated</span>
                            </div>
                            <div class="log-row">
                                <span class="log-time">[14:15:32]</span>
                                <span class="log-tag">COMPLIANCE</span>
                                <span>Validated statutory tables (BIR, SSS, PhilHealth)</span>
                            </div>
                            <div class="log-row">
                                <span class="log-time">[14:15:45]</span>
                                <span class="log-tag warn">SECURITY</span>
                                <span style="color:var(--amber);">Blocked an unauthorized file-access attempt</span>
                            </div>
                            <div class="log-row">
                                <span class="log-time">[14:16:01]</span>
                                <span class="log-tag">AUDIT</span>
                                <span>Admin sign-in verified</span>
                            </div>
                        </div>
                        
                        <div class="obs-grid">
                            <div class="obs-chart">
                                <div class="obs-chart-title">CPU Utilization</div>
                                <div style="font-size:1.5rem; font-weight:700; color:#fff; font-family:var(--mono);">1.4%</div>
                                <div style="margin-top:10px;">
                                    <svg viewBox="0 0 100 30" width="100%" height="24" fill="none">
                                        <path d="M0,25 Q15,5 30,20 T60,10 T90,25 T100,5" stroke="var(--green)" stroke-width="2" />
                                    </svg>
                                </div>
                            </div>
                            <div class="obs-chart">
                                <div class="obs-chart-title">Active Systems</div>
                                <div style="font-size:1.5rem; font-weight:700; color:#fff; font-family:var(--mono);">8 / 8</div>
                                <div style="margin-top:10px;">
                                    <svg viewBox="0 0 100 30" width="100%" height="24" fill="none">
                                        <rect x="5" y="5" width="10" height="25" fill="var(--blue)" />
                                        <rect x="20" y="8" width="10" height="22" fill="var(--blue)" />
                                        <rect x="35" y="3" width="10" height="27" fill="var(--blue)" />
                                        <rect x="50" y="6" width="10" height="24" fill="var(--blue)" />
                                        <rect x="65" y="4" width="10" height="26" fill="var(--blue)" />
                                        <rect x="80" y="2" width="10" height="28" fill="var(--blue)" />
                                    </svg>
                                </div>
                            </div>
                            <div class="obs-chart">
                                <div class="obs-chart-title">File Guard Status</div>
                                <div style="font-size:1.1rem; font-weight:700; color:var(--green); font-family:var(--mono); margin-top:4px;">TAMPER-FREE</div>
                                <div style="margin-top:15px;">
                                    <svg viewBox="0 0 100 10" width="100%" height="10" fill="none">
                                        <line x1="0" y1="5" x2="100" y2="5" stroke="var(--green)" stroke-dasharray="4 4" stroke-width="2" />
                                    </svg>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- TIMELINE SECTION 4: AI COMPANION AGENTS (CURSOR AGENT STYLE) -->
<section class="timeline-section" id="agents" style="background: rgba(0,0,0,0.15);">
    <div class="timeline-line"></div>
    
    <div class="revamp-container">
        <div class="revamp-grid reverse">
            <div>
                <!-- Cursor Editor Agent Mockup -->
                <div class="editor-mockup">
                    <div class="editor-sidebar">
                        <div class="editor-sidebar-title">Active Files</div>
                        <div class="editor-sidebar-item"><i data-lucide="file-text" size="12"></i> onboarding.md</div>
                        <div class="editor-sidebar-item"><i data-lucide="file-code" size="12"></i> tax_rules.php</div>
                        <div class="editor-sidebar-item active"><i data-lucide="file-text" size="12"></i> travel_policy.md</div>
                    </div>
                    <div class="editor-content">
                        <div>
                            <div style="font-family:var(--mono); font-size:0.7rem; color:var(--text-dim); margin-bottom:12px;">AI Assistant</div>
                            <div class="agent-bubble">
                                <span style="color:var(--green); font-weight:600;">Draft allowance rules:</span> Create travel allowance rules matching BIR de minimis guidelines.
                            </div>
                            <div style="margin-top: 12px; font-size:0.75rem; color:var(--text-mid);">
                                <strong>Thought (3s):</strong><br>
                                • Read PH tax code section 34<br>
                                • Drafted rules inside <code>travel_policy.md</code>
                            </div>
                        </div>
                        <div class="agent-input-box">
                            <span>Ask a follow up...</span>
                            <i data-lucide="arrow-up" size="14"></i>
                        </div>
                    </div>
                </div>
            </div>

            <div>
                <span class="pill-label">Contextual AI Copilot</span>
                <h2 style="font-size: clamp(2rem, 4vw, 2.75rem); font-family: var(--sans); font-weight: 700; line-height: 1.15; margin-bottom: 16px;">AI that drafts your HR paperwork.</h2>
                <p style="color: var(--text-mid); font-size: 1.05rem; line-height: 1.6;">Hand the repetitive work — drafting contracts and policies, answering employee questions, and sorting support tickets — to a built-in AI assistant.</p>

                <div class="revamp-list">
                    <div class="revamp-item">
                        <i data-lucide="sparkles"></i>
                        <div>
                            <h4>Drafts contracts &amp; policies for you</h4>
                            <p>It writes employment contracts, allowance policies, and review forms using your company details and current Philippine tax rules.</p>
                        </div>
                    </div>
                    <div class="revamp-item">
                        <i data-lucide="bot"></i>
                        <div>
                            <h4>Answers employee questions 24/7</h4>
                            <p>Staff get instant answers to common HR questions; anything complex is routed straight to your HR team.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- TIMELINE SECTION 5: COMPLETE CODEBASE INDEXING (CURSOR CODEBASE STYLE) -->
<section class="timeline-section" id="codebase">
    <div class="timeline-line"></div>
    
    <div class="revamp-container">
        <div class="revamp-grid">
            <div>
                <span class="pill-label">Search and Index</span>
                <h2 style="font-size: clamp(2rem, 4vw, 2.75rem); font-family: var(--sans); font-weight: 700; line-height: 1.15; margin-bottom: 16px;">Find any employee record in seconds.</h2>
                <p style="color: var(--text-mid); font-size: 1.05rem; line-height: 1.6;">Every employee profile, org chart, salary history, and audit log is instantly searchable. Ask a question and get the answer — no digging through folders or spreadsheets.</p>

                <div class="revamp-list">
                    <div class="revamp-item">
                        <i data-lucide="search"></i>
                        <div>
                            <h4>Instant company-wide search</h4>
                            <p>Pull up a contract, a leave balance, or a past payslip in seconds — no manual hunting through files.</p>
                        </div>
                    </div>
                </div>
            </div>

            <div>
                <!-- Mockup Search bar -->
                <div style="background:#0e101b; border: 1px solid var(--border2); border-radius: 12px; padding: 24px; text-align: left; font-family: var(--mono); font-size: 0.8rem;">
                    <div style="color:var(--text-dim); margin-bottom:12px;">Search your workspace</div>
                    <div style="background:#05070c; border: 1px solid var(--border3); padding: 12px 16px; border-radius: 6px; color:#fff; display:flex; justify-content:space-between;">
                        <span>Show Maria Reyes' latest payslip</span>
                        <span style="color:var(--green);">Ctrl + K</span>
                    </div>
                    <div style="margin-top:16px; color:var(--text-mid); line-height:1.7;">
                        <span style="color:var(--green);">Found 1 record:</span><br>
                        <span style="color:var(--text-dim);">Directory ▸ Maria Reyes ▸ Payslips ▸ July 2026 · Net ₱54,200</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- SECTION 5.5: US VS THEM COMPARISON -->
<section class="story-section" id="compare" style="border-top: 1px solid var(--border3);">
    <div class="story-container">
        <h2>The Legacy Way vs. The Respawn Way</h2>
        <p class="sub" style="text-align: center; margin-bottom: 50px;">Why elite teams refuse to use legacy HR software.</p>

        <div class="comparison-table-wrapper" style="overflow-x: auto; background: rgba(255,255,255,0.02); border: 1px solid var(--border2); border-radius: 12px; padding: 20px;">
            <table style="width: 100%; border-collapse: collapse; min-width: 600px; text-align: left;">
                <thead>
                    <tr>
                        <th style="padding: 20px; border-bottom: 1px solid var(--border2); color: var(--text-mid); font-size: 0.9rem; text-transform: uppercase; letter-spacing: 0.05em; width: 20%;">Focus Area</th>
                        <th style="padding: 20px; border-bottom: 1px solid var(--border2); color: #ff4a4a; font-size: 1.1rem; width: 40%;"><i class="fa-solid fa-xmark"></i> Legacy HR Tech</th>
                        <th style="padding: 20px; border-bottom: 1px solid var(--border2); color: var(--green); font-size: 1.2rem; width: 40%;"><i class="fa-solid fa-check-double"></i> Respawn Logics</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td style="padding: 20px; border-bottom: 1px solid var(--border2); color: var(--text); font-weight: 600;">Architecture</td>
                        <td style="padding: 20px; border-bottom: 1px solid var(--border2); color: var(--text-mid);">"Frankenstein" integrations. You buy an ATS, an HRIS, and a Payroll system from 3 different vendors and try to tape them together.</td>
                        <td style="padding: 20px; border-bottom: 1px solid var(--border2); color: var(--text); background: rgba(0,224,122,0.03);"><strong>A Unified Ecosystem.</strong> Candidate data flows natively from ATS directly into the Employee Directory and straight into Payroll. Zero double-entry.</td>
                    </tr>
                    <tr>
                        <td style="padding: 20px; border-bottom: 1px solid var(--border2); color: var(--text); font-weight: 600;">User Experience</td>
                        <td style="padding: 20px; border-bottom: 1px solid var(--border2); color: var(--text-mid);">Clunky, slow interfaces that look like they were built in 2005. Employees dread using them.</td>
                        <td style="padding: 20px; border-bottom: 1px solid var(--border2); color: var(--text); background: rgba(0,224,122,0.03);"><strong>Modern &amp; Effortless.</strong> A fast, clean interface your team actually enjoys using — no training manual required.</td>
                    </tr>
                    <tr>
                        <td style="padding: 20px; border-bottom: 1px solid var(--border2); color: var(--text); font-weight: 600;">PH Compliance</td>
                        <td style="padding: 20px; border-bottom: 1px solid var(--border2); color: var(--text-mid);">Generic payroll engines or hardcoded rules that break entirely whenever BIR, SSS, or PhilHealth changes rates.</td>
                        <td style="padding: 20px; border-bottom: 1px solid var(--border2); color: var(--text); background: rgba(0,224,122,0.03);"><strong>Versioned Statutory Tables.</strong> Built specifically for the Philippines. Statutory changes are data updates, keeping historical runs 100% accurate.</td>
                    </tr>
                    <tr>
                        <td style="padding: 20px; border-bottom: 1px solid var(--border2); color: var(--text); font-weight: 600;">Data Security</td>
                        <td style="padding: 20px; border-bottom: 1px solid var(--border2); color: var(--text-mid);">Weak access controls. Everything ends up exported to insecure Excel spreadsheets to make reporting work.</td>
                        <td style="padding: 20px; border-bottom: 1px solid var(--border2); color: var(--text); background: rgba(0,224,122,0.03);"><strong>Bank-Grade &amp; Audit-Ready.</strong> Bank-level encryption, strict role-based access, and tamper-proof activity logs on every record.</td>
                    </tr>
                    <tr>
                        <td style="padding: 20px; border-bottom: none; color: var(--text); font-weight: 600;">Pricing Model</td>
                        <td style="padding: 20px; border-bottom: none; color: var(--text-mid);">Predatory "Pay-Per-Module" pricing. Want the ATS? That's extra. Need Service Desk? Another contract.</td>
                        <td style="padding: 20px; border-bottom: none; color: var(--text); background: rgba(0,224,122,0.03);"><strong>All-Inclusive Suite.</strong> You get the entire platform—Core HR, ATS, Payroll, ELR, Service Desk, and Attendance—out of the box.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</section>

<!-- TIMELINE SECTION 6: SYSTEM CHANGELOG (CURSOR CHANGELOG STYLE) -->
<section class="timeline-section" id="changelog" style="border-top: 1px solid var(--border3); background: rgba(0,0,0,0.1);">
    <div class="revamp-container" style="padding-left:0; text-align:center;">
        <h2 style="font-size: clamp(2rem, 4vw, 2.75rem); font-family: var(--sans); font-weight: 700; margin-bottom: 12px;">System Changelog</h2>
        <p style="color: var(--text-mid); font-size: 1.05rem; max-width: 600px; margin: 0 auto 40px;">Recent updates deployed to the Respawn Logics engine.</p>
        
        <div class="changelog-row">
            <div class="changelog-card">
                <div class="changelog-header">
                    <span class="changelog-version">v2.5.0</span>
                    <span class="changelog-date">Jul 10, 2026</span>
                </div>
                <div class="changelog-title">Fail-Loud Storage Check</div>
                <p class="changelog-desc">Enforced security bounds. File uploads fail instantly if configured storage paths fall inside the public web root directory.</p>
            </div>
            
            <div class="changelog-card">
                <div class="changelog-header">
                    <span class="changelog-version">v2.4.0</span>
                    <span class="changelog-date">Jun 30, 2026</span>
                </div>
                <div class="changelog-title">Versioned PH Tax Tables</div>
                <p class="changelog-desc">Integrated dynamically versioned tax calculation schemas for BIR, SSS, and PhilHealth computations, retaining historical runs accuracy.</p>
            </div>
            
            <div class="changelog-card">
                <div class="changelog-header">
                    <span class="changelog-version">v2.3.0</span>
                    <span class="changelog-date">Jun 12, 2026</span>
                </div>
                <div class="changelog-title">ELR Support ticket pipeline</div>
                <p class="changelog-desc">Launched unified Employee Relations queues with automated case logging, status checks, and encrypted attachments.</p>
            </div>
        </div>
        
        <div style="margin-top:40px;">
            <a href="#" style="color:var(--green); font-family:var(--mono); text-decoration:none; font-size:0.9rem; font-weight:600;">See all updates →</a>
        </div>
    </div>
</section>

<!-- PAYROLL PREVIEW MOCKUP SECTION -->
<section class="timeline-section" id="payroll-preview" style="border-top: 1px solid var(--border3);">
    <div style="max-width:1200px; margin:0 auto; text-align:center; margin-bottom: 60px;">
        <span class="pill-label">Live Preview</span>
        <h2 style="font-size: clamp(2rem, 4vw, 2.75rem); font-family: var(--sans); font-weight: 700; margin-bottom: 16px; line-height:1.15;">Your payroll engine.<br>Crystal clear.</h2>
        <p style="color: var(--text-mid); font-size: 1.05rem; max-width: 580px; margin: 0 auto;">Run full payroll cycles with BIR, SSS, PhilHealth, and Pag-IBIG contributions automatically computed. Every deduction. Every withholding. Zero spreadsheets.</p>
    </div>
    <div class="payroll-mockup-wrap">
        <div class="payroll-topbar">
            <div style="display:flex; gap:6px;">
                <div style="width:10px; height:10px; border-radius:50%; background:#ff5f57;"></div>
                <div style="width:10px; height:10px; border-radius:50%; background:#febc2e;"></div>
                <div style="width:10px; height:10px; border-radius:50%; background:#28c840;"></div>
            </div>
            <div class="payroll-tabs">
                <div class="payroll-tab active">July 2026 Payroll</div>
                <div class="payroll-tab">June 2026</div>
                <div class="payroll-tab">May 2026</div>
            </div>
            <div style="margin-left:auto; color: var(--green); font-weight:600;"><span style="color:var(--text-dim);">Status:</span> FINALIZED</div>
        </div>
        <div class="payroll-body">
            <div class="payroll-sidebar">
                <div class="payroll-sidebar-item active"><i data-lucide="layout-dashboard" size="14"></i> Overview</div>
                <div class="payroll-sidebar-item"><i data-lucide="users" size="14"></i> Employees</div>
                <div class="payroll-sidebar-item"><i data-lucide="calculator" size="14"></i> Deductions</div>
                <div class="payroll-sidebar-item"><i data-lucide="receipt" size="14"></i> Payslips</div>
                <div class="payroll-sidebar-item"><i data-lucide="file-check" size="14"></i> BIR Form 1601-C</div>
                <div class="payroll-sidebar-item"><i data-lucide="landmark" size="14"></i> SSS/PhilHealth</div>
            </div>
            <div class="payroll-main">
                <div class="payroll-stat-bar">
                    <div class="payroll-stat">
                        <div class="payroll-stat-key">// GROSS PAY</div>
                        <div class="payroll-stat-val green">₱ 2,847,500</div>
                    </div>
                    <div class="payroll-stat">
                        <div class="payroll-stat-key">// TOTAL DEDUCTIONS</div>
                        <div class="payroll-stat-val">₱ 384,210</div>
                    </div>
                    <div class="payroll-stat">
                        <div class="payroll-stat-key">// NET DISBURSED</div>
                        <div class="payroll-stat-val green">₱ 2,463,290</div>
                    </div>
                </div>
                <div class="payroll-row-head">
                    <span>EMPLOYEE</span>
                    <span>BASIC</span>
                    <span>DEDUCTIONS</span>
                    <span>NET PAY</span>
                    <span>STATUS</span>
                </div>
                <div class="payroll-row">
                    <div style="display:flex; align-items:center; gap:10px;"><div style="width:28px; height:28px; border-radius:50%; background: rgba(0,224,122,0.15); display:flex; align-items:center; justify-content:center; font-size:0.65rem; font-weight:700; color:var(--green);">AM</div><div><div style="font-weight:600;">Alex Mercer</div><div style="font-size:0.7rem; color:var(--text-dim); font-family:var(--mono);">Senior Engineer</div></div></div>
                    <div style="font-family:var(--mono);">₱ 85,000</div>
                    <div style="font-family:var(--mono); color:var(--text-dim);">₱ 12,450</div>
                    <div style="font-family:var(--mono); color:var(--green); font-weight:700;">₱ 72,550</div>
                    <div><span class="payroll-badge paid">● PAID</span></div>
                </div>
                <div class="payroll-row">
                    <div style="display:flex; align-items:center; gap:10px;"><div style="width:28px; height:28px; border-radius:50%; background: rgba(79,142,247,0.15); display:flex; align-items:center; justify-content:center; font-size:0.65rem; font-weight:700; color:var(--blue);">SC</div><div><div style="font-weight:600;">Sarah Chen</div><div style="font-size:0.7rem; color:var(--text-dim); font-family:var(--mono);">HR Manager</div></div></div>
                    <div style="font-family:var(--mono);">₱ 72,000</div>
                    <div style="font-family:var(--mono); color:var(--text-dim);">₱ 10,890</div>
                    <div style="font-family:var(--mono); color:var(--green); font-weight:700;">₱ 61,110</div>
                    <div><span class="payroll-badge paid">● PAID</span></div>
                </div>
                <div class="payroll-row">
                    <div style="display:flex; align-items:center; gap:10px;"><div style="width:28px; height:28px; border-radius:50%; background: rgba(245,166,35,0.15); display:flex; align-items:center; justify-content:center; font-size:0.65rem; font-weight:700; color:var(--amber);">DK</div><div><div style="font-weight:600;">David Kim</div><div style="font-size:0.7rem; color:var(--text-dim); font-family:var(--mono);">Operations Lead</div></div></div>
                    <div style="font-family:var(--mono);">₱ 90,000</div>
                    <div style="font-family:var(--mono); color:var(--text-dim);">₱ 14,200</div>
                    <div style="font-family:var(--mono); color:var(--green); font-weight:700;">₱ 75,800</div>
                    <div><span class="payroll-badge pending">⊙ PENDING</span></div>
                </div>
                <div class="payroll-row">
                    <div style="display:flex; align-items:center; gap:10px;"><div style="width:28px; height:28px; border-radius:50%; background: rgba(155,109,255,0.15); display:flex; align-items:center; justify-content:center; font-size:0.65rem; font-weight:700; color:var(--purple);">MR</div><div><div style="font-weight:600;">Maria Reyes</div><div style="font-size:0.7rem; color:var(--text-dim); font-family:var(--mono);">Finance Analyst</div></div></div>
                    <div style="font-family:var(--mono);">₱ 58,000</div>
                    <div style="font-family:var(--mono); color:var(--text-dim);">₱ 8,700</div>
                    <div style="font-family:var(--mono); color:var(--green); font-weight:700;">₱ 49,300</div>
                    <div><span class="payroll-badge draft">○ DRAFT</span></div>
                </div>
            </div>
        </div>
    </div>
</section>


<!-- SECURITY & TRUST SECTION -->
<div class="trust-section">
    <div class="trust-inner">
        <div class="trust-header">
            <span class="pill-label blue" style="margin: 0 auto 16px;">Security and Compliance</span>
            <h2 style="font-size: clamp(1.75rem, 3.5vw, 2.5rem); font-family: var(--sans); font-weight: 700; line-height: 1.15; margin-bottom: 20px;">Your employees' data, properly protected.</h2>
            <p style="color: var(--text-mid); font-size: 1.05rem; line-height: 1.6;">Employee records are the most sensitive information your organization holds. Everything we build starts from that — and guards it accordingly.</p>
        </div>
        <div class="trust-shield">
            <div class="trust-badge">
                <i data-lucide="hard-drive"></i>
                <h4>Private File Storage</h4>
                <p>Employee documents are kept in a private, isolated location — never anywhere a web browser could reach them.</p>
            </div>
            <div class="trust-badge">
                <i data-lucide="lock"></i>
                <h4>Bank-Level Encryption</h4>
                <p>Every payslip, contract, and 201 file is encrypted with the same AES-256 standard banks use.</p>
            </div>
            <div class="trust-badge">
                <i data-lucide="eye-off"></i>
                <h4>Role-Based Access</h4>
                <p>HR admins, managers, and employees each see only what their role is allowed to — nothing more.</p>
            </div>
            <div class="trust-badge">
                <i data-lucide="scroll-text"></i>
                <h4>Tamper-Proof Audit Trail</h4>
                <p>Every change, login, and file access is permanently recorded and can't be altered after the fact.</p>
            </div>
        </div>
    </div>
</div>

<!-- SECTION 6: PLATFORM CTA -->
<section class="gaming-cta" style="border-top: 1px solid var(--border3);">
    <div class="story-container">
        <h2 style="font-size: 2.5rem; color: var(--text); margin-bottom: 20px;">A Better Way to Manage Operations</h2>
        <p style="color: var(--text-mid); font-size: 1.125rem; max-width: 600px; margin: 0 auto;">Equip your operations team with a unified command center. No more juggling ten different tabs just to onboard a single employee or process payroll.</p>
        
        <div class="gaming-flow">
            <div class="gaming-step"><i data-lucide="map-pin"></i> Get Set Up</div>
            <i data-lucide="chevron-right" class="gaming-divider"></i>
            <div class="gaming-step"><i data-lucide="users"></i> Scale Your Team</div>
            <i data-lucide="chevron-right" class="gaming-divider"></i>
            <div class="gaming-step"><i data-lucide="trending-up"></i> Track Performance</div>
            <i data-lucide="chevron-right" class="gaming-divider"></i>
            <div class="gaming-step"><i data-lucide="shield-alert"></i> Unified Support</div>
        </div>
    </div>
</section>

<hr class="divider">

<!-- STORY -->
<div class="story-section" id="story">
    <div class="story-container story-inner">
        <div>
            <div class="eyebrow">// THE CONCEPT</div>
            <h2 class="section-h" style="margin-bottom: 24px;">Built for teams that want clarity, not corporate bloat.</h2>
            <p style="font-size: 1rem; color: var(--text-mid); line-height: 1.8; margin-bottom: 20px; text-align: left;">
                Every time scattered tools and messy spreadsheets slow you down, you need a <strong style="color:var(--text)">respawn</strong> — a clean slate and another shot at doing it right. We think every organization deserves that reset: one tidy system, a clear history of every change, and the confidence that things just work.
            </p>
            <p style="font-size: 1rem; color: var(--text-mid); line-height: 1.8; margin-bottom: 20px; text-align: left;">
                The <strong style="color:var(--text)">Logics</strong> half keeps us grounded. Payroll, compliance, and personal data demand precision — accurate calculations, a full audit trail, and airtight security. We pair a genuinely modern interface with that discipline.
            </p>
            <p style="font-size: 1rem; color: var(--text); line-height: 1.8; font-weight: 500; text-align: left;">
                Built in the Philippines <img src="https://flagcdn.com/ph.svg" width="20" alt="PH" style="vertical-align: middle; margin-left: 2px; margin-top: -3px; border-radius: 2px;">, for organizations that scale with precision.
            </p>
        </div>

        <div class="terminal">
            <div class="term-bar">
                <div class="t-dot r"></div>
                <div class="t-dot y"></div>
                <div class="t-dot g"></div>
                <span class="term-file">respawn-logics ~ system.log</span>
            </div>
            <div class="term-body" style="text-align: left;">
                <div class="t-row"><span class="t-p">▶</span><span class="t-c">./respawn <span style="color:var(--green)">--boot</span></span></div>
                <div class="t-o t-cm"># Initializing core modules...</div>
                <div class="t-o"><span style="color:var(--green)">✔</span> onboarding.engine &nbsp;&nbsp; <span class="t-v">READY</span></div>
                <div class="t-o"><span style="color:var(--green)">✔</span> payroll.engine &nbsp;&nbsp;&nbsp;&nbsp;&nbsp; <span class="t-v">READY</span></div>
                <div class="t-o"><span style="color:var(--green)">✔</span> ats.pipeline &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; <span class="t-v">READY</span></div>
                <div class="t-o"><span style="color:var(--green)">✔</span> ai.intelligence &nbsp;&nbsp;&nbsp;&nbsp; <span class="t-v">READY</span></div>
                <div class="t-o"><span style="color:var(--green)">✔</span> rbac.security &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; <span class="t-v">READY</span></div>
                <div class="t-o"><span style="color:var(--green)">✔</span> esm.helpdesk &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; <span class="t-v">READY</span></div>
                <div class="t-gap"></div>
                <div class="t-o t-cm"># 17 modules active.</div>
                <div class="t-o t-cm"># Tenant isolation: ON</div>
                <div class="t-o t-cm"># Audit trail: ARMED</div>
                <div class="t-gap"></div>
                <div class="t-row"><span class="t-p">▶</span><span class="t-c">status <span style="color:var(--green)">--all</span></span></div>
                <div class="t-o"><span class="t-v">SYSTEM</span> All systems nominal.</div>
                <div class="t-o"><span class="t-v">SERVER</span> Uptime: <span style="color:var(--green)">99.9%</span></div>
                <div class="t-gap"></div>
                <div class="t-row"><span class="t-p">▶</span><span class="t-cursor"></span></div>
            </div>
        </div>
    </div>
</div>


<!-- PLAN MODAL -->
<div class="plan-modal-overlay" id="planModal" role="dialog" aria-modal="true" aria-label="Choose a plan">
    <div class="plan-modal">
        <button class="plan-modal-close" id="planModalClose" aria-label="Close">&times;</button>
        <div class="plan-modal-title">Choose your starting point.</div>
        <div class="plan-modal-sub">// both plans are free during the beta period</div>

        <div class="plan-modal-grid">
            <!-- SOLO -->
            <div class="plan-card">
                <div class="plan-card-label solo"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="4"/><path d="M6 20v-2a4 4 0 0 1 8 0v2"/></svg> Solo Founder</div>
                <div class="plan-card-h">Build your empire.</div>
                <div class="plan-card-p">Perfect for solo developers, indie hackers, and single-member startups who need an enterprise-grade HRIS to start right.</div>
                <ul class="plan-card-perks">
                    <li>1 Sandbox Environment</li>
                    <li>1 Administrator Seat</li>
                    <li>All Core Modules included</li>
                    <li>Community Support</li>
                </ul>
                <div class="plan-card-price">₱0</div>
                <div class="plan-card-price-sub">Free for first 3 months</div>
                <?php if ($loggedIn): ?>
                    <a href="<?= url('/frontend/dist/index.html?v=' . time() . '#/dashboard') ?>" class="btn-primary" style="width:100%; justify-content:center;">
                        <i data-lucide="play"></i> Go to Dashboard
                    </a>
                <?php else: ?>
                    <a href="<?= url('/register.php') ?>" class="btn-primary" style="width:100%; justify-content:center;">
                        <i data-lucide="rocket"></i> Create Solo Workspace
                    </a>
                <?php endif; ?>
            </div>

            <!-- ENTERPRISE BETA -->
            <div class="plan-card">
                <div class="plan-card-label beta"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 3H5a2 2 0 0 0-2 2v4m6-6h10a2 2 0 0 1 2 2v4M9 3v18m0 0h10a2 2 0 0 0 2-2v-4M9 21H5a2 2 0 0 1-2-2v-4m0 0h18"/></svg> Private Beta &mdash; Limited Slots</div>
                <div class="plan-card-h">Join before we<br>go public.</div>
                <div class="plan-card-p">We're onboarding select enterprise partners. Every feature completely free while we battle-test the platform together.</div>
                <ul class="plan-card-perks">
                    <li>Unlimited employee seats</li>
                    <li>Batch structure onboarding</li>
                    <li>Direct line to the dev team</li>
                    <li>Priority onboarding support</li>
                </ul>
                <div class="plan-card-price">₱0</div>
                <div class="plan-card-price-sub">During beta period</div>
                <?php if ($loggedIn): ?>
                    <a href="<?= url('/frontend/dist/index.html?v=' . time() . '#/dashboard') ?>" class="btn-primary" style="width:100%; justify-content:center;">
                        <i data-lucide="play"></i> Go to Dashboard
                    </a>
                <?php else: ?>
                    <a href="<?= url('/frontend/dist/index.html#/setup') ?>" class="btn-primary" style="width:100%; justify-content:center;">
                        <i data-lucide="building"></i> Claim Enterprise Slot
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <div class="plan-modal-footer">
            Already have an account? <a href="<?= url('/login.php') ?>">Sign in &rarr;</a>
        </div>
    </div>
</div>

<!-- BIG TYPE CTA -->
<section class="bigtype-cta">
    <div class="bigtype-inner">
        <div class="bigtype-overline">Ready when you are</div>

        <h2 class="bigtype-headline">
            Let's run
            <span class="bt-accent">operations</span>
            the right way.
        </h2>

        <div class="bigtype-sub">
            <span>( WHERE COMPLIANCE MEETS CLARITY )</span>
        </div>

        <div class="bigtype-actions">
            <?php if ($loggedIn): ?>
                <a href="<?= url('/frontend/dist/index.html?v=' . time() . '#/dashboard') ?>" class="btn-primary" style="font-size:1rem; padding: 14px 32px;">
                    <i data-lucide="play"></i> Open Dashboard
                </a>
            <?php else: ?>
                <button id="openPlanModal" class="btn-primary" style="font-size:1rem; padding: 14px 32px; border:none; cursor:pointer;">
                    <i data-lucide="zap"></i> Get Started Free
                </button>
                <a href="<?= url('/login.php') ?>" class="btn-ghost" style="font-size:1rem; padding: 14px 32px;">
                    Sign In
                </a>
            <?php endif; ?>
        </div>
    </div>
    <div class="bigtype-scroll-hint">Scroll to footer</div>
</section>

<script>
(function() {
    var overlay = document.getElementById('planModal');
    var closeBtn = document.getElementById('planModalClose');
    var openBtn = document.getElementById('openPlanModal');
    if (!overlay) return;

    function open() {
        overlay.classList.add('open');
        document.body.style.overflow = 'hidden';
    }
    function close() {
        overlay.classList.remove('open');
        document.body.style.overflow = '';
    }

    if (openBtn) openBtn.addEventListener('click', open);
    if (closeBtn) closeBtn.addEventListener('click', close);
    overlay.addEventListener('click', function(e) {
        if (e.target === overlay) close();
    });
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') close();
    });
})();
</script>

<!-- FOOTER -->
<footer>
    <div class="footer-inner">
        <div class="footer-grid">
            <div class="footer-col">
                <h4>Platform Modules</h4>
                <ul>
                    <li><a href="<?= url('/solutions.php?module=core-hr') ?>">Core HR & People</a></li>
                    <li><a href="<?= url('/solutions.php?module=ats') ?>">ATS Pipeline</a></li>
                    <li><a href="<?= url('/solutions.php?module=payroll') ?>">Enterprise Payroll</a></li>
                    <li><a href="<?= url('/solutions.php?module=service-desk') ?>">Service Desk</a></li>
                    <li><a href="<?= url('/solutions.php?module=employee-relations') ?>">Employee Relations</a></li>
                    <li><a href="<?= url('/solutions.php?module=attendance') ?>">Attendance & Leaves</a></li>
                </ul>
            </div>
            <div class="footer-col">
                <h4>Portals</h4>
                <ul>
                    <li><a href="<?= url('/login.php') ?>">Employee Login</a></li>
                    <li><a href="<?= url('/register.php') ?>">Create Workspace</a></li>
                    <li><a href="<?= url('/frontend/dist/index.html#/setup') ?>">Enterprise Onboarding</a></li>
                </ul>
            </div>
            <div class="footer-col">
                <h4>Resources</h4>
                <ul>
                    <li><a href="<?= url('/submit_ticket.php') ?>">Help Center</a></li>
                </ul>
            </div>
            <div class="footer-col">
                <h4>Company</h4>
                <ul>
                    <li><a href="#story">Our Story</a></li>
                    <li><a href="design.php">Design Philosophy</a></li>
                    <li><a href="#beta">Pricing</a></li>
                </ul>
            </div>
            <div class="footer-col">
                <h4>Legal</h4>
                <ul>
                    <li><a href="<?= url('/privacy.php') ?>">Privacy Policy</a></li>
                    <li><a href="<?= url('/terms.php') ?>">Terms of Service</a></li>
                </ul>
            </div>
        </div>
        
        <div class="footer-bottom">
            <div class="footer-copy">
                © <?= date('Y') ?> Respawn Logics Inc. &nbsp;·&nbsp; Built in the Philippines <img src="https://flagcdn.com/ph.svg" width="16" alt="PH" style="vertical-align: middle; margin-left: 2px; margin-top: -2px; border-radius: 2px;"> &nbsp;·&nbsp; Powered by Gemini
            </div>
            <div class="footer-socials">
                <a href="#"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 8a6 6 0 0 1 6 6v7h-4v-7a2 2 0 0 0-2-2 2 2 0 0 0-2 2v7h-4v-7a6 6 0 0 1 6-6z"/><rect width="4" height="12" x="2" y="9"/><circle cx="4" cy="4" r="2"/></svg></a>
                <a href="#"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4l11.733 16h4.267l-11.733 -16z"/><path d="M4 20l6.768 -6.768m2.46 -2.46l6.772 -6.772"/></svg></a>
                <a href="#"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 22v-4a4.8 4.8 0 0 0-1-3.2c3 0 6-2 6-5.5.08-1.25-.27-2.48-1-3.5.28-1.15.28-2.35 0-3.5 0 0-1 0-3 1.5-2.64-.5-5.36-.5-8 0C6 2 5 2 5 2c-.3 1.15-.3 2.35 0 3.5A5.403 5.403 0 0 0 4 9c0 3.5 3 5.5 6 5.5-.39.49-.68 1.05-.85 1.65-.17.6-.22 1.23-.15 1.85v4"/><path d="M9 18c-4.51 2-5-2-7-2"/></svg></a>
            </div>
        </div>
    </div>
</footer>

<script>
(function() {
    const tabs = document.querySelectorAll('#module-tabs .tab-btn');
    const cards = document.querySelectorAll('.mod-card');
    tabs.forEach(btn => {
        btn.addEventListener('click', function() {
            tabs.forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            const filter = this.dataset.filter;
            cards.forEach(card => {
                const match = filter === 'all' || card.dataset.filter === filter;
                card.style.display = match ? '' : 'none';
                // Handle wide class visibility in grid
                if (match && card.classList.contains('wide') && filter !== 'all') {
                    card.style.gridColumn = 'span 1';
                } else if (match && card.classList.contains('wide')) {
                    card.style.gridColumn = 'span 2';
                }
            });
        });
    });
})();

// Animated counters
(function() {
    const counters = document.querySelectorAll('.counter');
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting && !entry.target.dataset.done) {
                entry.target.dataset.done = 'true';
                const target = parseInt(entry.target.dataset.target, 10);
                const duration = 1800;
                const start = performance.now();
                function tick(now) {
                    const elapsed = now - start;
                    const progress = Math.min(elapsed / duration, 1);
                    const eased = 1 - Math.pow(1 - progress, 3);
                    entry.target.textContent = Math.floor(eased * target);
                    if (progress < 1) requestAnimationFrame(tick);
                    else entry.target.textContent = target;
                }
                requestAnimationFrame(tick);
            }
        });
    }, { threshold: 0.5 });
    counters.forEach(c => observer.observe(c));
})();

// Fade-in-up on scroll
(function() {
    const style = document.createElement('style');
    style.textContent = `
        .fade-up { opacity: 0; transform: translateY(28px); transition: opacity 0.7s ease, transform 0.7s ease; }
        .fade-up.visible { opacity: 1; transform: translateY(0); }
    `;
    document.head.appendChild(style);
    const els = document.querySelectorAll('.stat-block, .testimonial-card, .trust-badge, .changelog-card');
    const obs = new IntersectionObserver((entries) => {
        entries.forEach((e, i) => {
            if (e.isIntersecting) {
                setTimeout(() => e.target.classList.add('visible'), 80);
                obs.unobserve(e.target);
            }
        });
    }, { threshold: 0.15 });
    els.forEach(el => { el.classList.add('fade-up'); obs.observe(el); });
})();

// Interactive Operations Center demo switcher
(function() {
    const sidebarItems = document.querySelectorAll('.demo-sidebar-item');
    const centerHeader = document.querySelector('.demo-center .demo-center-header');
    const centerPrompt = document.querySelector('.demo-center .demo-prompt-box');
    const centerBody = document.querySelector('.demo-center .demo-center-body');
    const centerInputText = document.querySelector('.demo-center .demo-center-input');
    const previewAddress = document.querySelector('.demo-preview .demo-address');
    const previewBody = document.querySelector('.demo-preview .demo-preview-body');

    const demoData = {
        "0": {
            header: "Payroll Run — July 2026",
            prompt: "run payroll for all active employees in workspace <strong style=\"color:var(--green)\">respawn-ph</strong> — apply July 2026 statutory tables, generate payslips, and flag anomalies",
            inputText: "Run payroll, process leaves, generate reports... ",
            address: "respawn-logics.local/payslips/july_2026",
            logs: `
                <div class="demo-prompt-box">
                    run payroll for all active employees in workspace <strong style="color:var(--green)">respawn-ph</strong> — apply July 2026 statutory tables, generate payslips, and flag anomalies
                </div>
                <div class="demo-log-line"><span class="dl-action">Read</span> <span class="dl-file">employees.json</span> <span style="color:var(--text-dim)">·</span> <span style="color:var(--text-dim)">47 active records</span></div>
                <div class="demo-log-line"><span class="dl-action">Read</span> <span class="dl-file">statutory_tables/2026.json</span></div>
                <div class="demo-log-line"><span class="dl-think">Computing BIR withholding tax for 47 employees...</span></div>
                <div class="demo-log-line"><span class="dl-ok">✔</span> <span>SSS / PhilHealth / Pag-IBIG deductions applied</span></div>
                <div class="demo-log-line"><span class="dl-warn">⚠</span> <span style="color:var(--amber)">1 anomaly flagged — Cruz, J. (retroactive adjustment)</span></div>
                <div class="demo-file-change">
                    <span class="fc-name">payslips/july_2026_batch.pdf</span>
                    <span><span class="fc-ins">+47</span>&nbsp;<span class="fc-del">-0</span></span>
                </div>
                <div class="demo-file-change">
                    <span class="fc-name">reports/bir_1601c_july.xlsx</span>
                    <span><span class="fc-ins">+1</span>&nbsp;<span class="fc-del">-0</span></span>
                </div>
                <div class="demo-log-line demo-done-msg" style="margin-top:4px;">
                    <span class="dl-ok">✔</span>
                    <span>Done. 47 payslips generated. ₱2,847,500 gross disbursed. 1 anomaly queued for review.</span>
                </div>
            `,
            preview: `
                <div class="payslip-preview">
                    <div class="payslip-preview-head">
                        <div>
                            <div class="payslip-preview-company">Respawn Logics Inc.</div>
                            <div style="font-size:0.62rem; color:var(--text-dim); margin-top:2px;">Payslip · July 2026</div>
                        </div>
                        <div class="payslip-preview-label">FINALIZED</div>
                    </div>
                    <div style="font-size:0.65rem; color:var(--text-dim); margin-bottom:12px;">Employee: <span style="color:var(--text);">Alex Mercer · Senior Engineer</span></div>
                    <div class="payslip-row"><span class="pr-key">Basic Pay</span><span class="pr-val">₱ 85,000.00</span></div>
                    <div class="payslip-row"><span class="pr-key">Allowances</span><span class="pr-val green">+ ₱ 5,000.00</span></div>
                    <div class="payslip-row"><span class="pr-key">SSS</span><span class="pr-val red">- ₱ 1,800.00</span></div>
                    <div class="payslip-row"><span class="pr-key">PhilHealth</span><span class="pr-val red">- ₱ 1,000.00</span></div>
                    <div class="payslip-row"><span class="pr-key">Pag-IBIG</span><span class="pr-val red">- ₱ 200.00</span></div>
                    <div class="payslip-row"><span class="pr-key">BIR Withholding</span><span class="pr-val red">- ₱ 9,450.00</span></div>
                    <div class="payslip-total">
                        <span class="pt-key">NET PAY</span>
                        <span class="pt-val">₱ 77,550.00</span>
                    </div>
                </div>
            `
        },
        "1": {
            header: "ATS Pipeline — Offer Generation",
            prompt: "move candidates in pipeline 'Batch 3' matching final-stage interview criteria to offer stage",
            inputText: "Search candidates, update pipelines, generate offer letters... ",
            address: "respawn-logics.local/ats/candidates/jenkins",
            logs: `
                <div class="demo-prompt-box">
                    move candidates in pipeline 'Batch 3' matching final-stage interview criteria to offer stage
                </div>
                <div class="demo-log-line"><span class="dl-action">Read</span> <span class="dl-file">candidates.db</span> <span style="color:var(--text-dim)">·</span> <span style="color:var(--text-dim)">14 applicants active</span></div>
                <div class="demo-log-line"><span class="dl-action">Filter</span> <span style="color:var(--text);">stage == 'Technical Interview'</span></div>
                <div class="demo-log-line"><span class="dl-think">Evaluating score criteria & feedback notes...</span></div>
                <div class="demo-log-line"><span class="dl-ok">✔</span> <span>Sarah Jenkins (92/100) and Liam Neeson (95/100) passed</span></div>
                <div class="demo-log-line"><span class="dl-warn">⚠</span> <span style="color:var(--amber)">Dave Ko (68/100) held in pipeline stage</span></div>
                <div class="demo-file-change">
                    <span class="fc-name">offers/offer_letter_jenkins.pdf</span>
                    <span><span class="fc-ins">+1</span>&nbsp;<span class="fc-del">-0</span></span>
                </div>
                <div class="demo-file-change">
                    <span class="fc-name">offers/offer_letter_neeson.pdf</span>
                    <span><span class="fc-ins">+1</span>&nbsp;<span class="fc-del">-0</span></span>
                </div>
                <div class="demo-log-line demo-done-msg" style="margin-top:4px;">
                    <span class="dl-ok">✔</span>
                    <span>Done. 2 candidates promoted. Offer letters generated.</span>
                </div>
            `,
            preview: `
                <div class="payslip-preview">
                    <div class="payslip-preview-head" style="border-bottom: 1px solid rgba(255,255,255,0.06); padding-bottom: 10px; margin-bottom: 12px;">
                        <div>
                            <div class="payslip-preview-company" style="font-size:0.75rem;">OFFER LETTER</div>
                            <div style="font-size:0.55rem; color:var(--text-dim); margin-top:2px;">Ref: OL-2026-079</div>
                        </div>
                        <div class="payslip-preview-label" style="color: var(--amber); border-color: rgba(251,191,36,0.3); background: rgba(251,191,36,0.05);">PENDING SIGN</div>
                    </div>
                    <div style="margin-top:8px; font-size:0.65rem; line-height: 1.5;">
                        <div style="font-weight: 600; color:var(--text); margin-bottom: 4px;">Candidate: Sarah Jenkins</div>
                        <div style="color: var(--text-dim); margin-bottom: 8px;">Position: Senior Frontend Engineer</div>
                        <p style="color: var(--text-dim); font-size: 0.6rem; line-height: 1.4; margin-bottom: 12px; font-family:var(--sans);">We are pleased to offer you employment at Respawn Logics Inc. on the terms detailed below:</p>
                        <div class="payslip-row" style="margin-bottom:4px;"><span class="pr-key">Base Salary</span><span class="pr-val">₱ 95,000.00</span></div>
                        <div class="payslip-row" style="margin-bottom:4px;"><span class="pr-key">Signing Bonus</span><span class="pr-val green">+ ₱ 50,000.00</span></div>
                        <div class="payslip-row" style="margin-bottom:4px;"><span class="pr-key">Stock Options</span><span class="pr-val green">1,200 Options</span></div>
                        <div class="payslip-row" style="margin-bottom:4px;"><span class="pr-key">Leave Credits</span><span class="pr-val">20 Days / year</span></div>
                        <div class="payslip-total" style="border-top:1px solid rgba(255,255,255,0.06); padding-top:8px; margin-top:12px;">
                            <span class="pt-key">MONTHLY GROSS</span>
                            <span class="pt-val">₱ 95,000.00</span>
                        </div>
                    </div>
                </div>
            `
        },
        "2": {
            header: "Employee Onboarding — ID Auto-Gen",
            prompt: "onboard new employee 'Mark Reyes' - generate employee ID, configure default RBAC, create workspace directory",
            inputText: "Add new employees, configure permissions, trigger emails... ",
            address: "respawn-logics.local/directory/mark-reyes",
            logs: `
                <div class="demo-prompt-box">
                    onboard new employee 'Mark Reyes' - generate employee ID, configure default RBAC, create workspace directory
                </div>
                <div class="demo-log-line"><span class="dl-action">Read</span> <span class="dl-file">contracts/signed_reyes.pdf</span> <span style="color:var(--text-dim)">·</span> <span style="color:var(--text-dim)">verified</span></div>
                <div class="demo-log-line"><span class="dl-action">Create</span> <span style="color:var(--text);">Employee Profile ID: RL-2026-0489</span></div>
                <div class="demo-log-line"><span class="dl-think">Assigning default Software Engineer RBAC group permissions...</span></div>
                <div class="demo-log-line"><span class="dl-ok">✔</span> <span>Encrypted 201 folder structure initialized</span></div>
                <div class="demo-log-line"><span class="dl-ok">✔</span> <span>Google Workspace & Slack invitation dispatch ok</span></div>
                <div class="demo-file-change">
                    <span class="fc-name">employees/rl-2026-0489.json</span>
                    <span><span class="fc-ins">+28</span>&nbsp;<span class="fc-del">-0</span></span>
                </div>
                <div class="demo-file-change">
                    <span class="fc-name">iam/roles/rl-2026-0489.json</span>
                    <span><span class="fc-ins">+4</span>&nbsp;<span class="fc-del">-0</span></span>
                </div>
                <div class="demo-log-line demo-done-msg" style="margin-top:4px;">
                    <span class="dl-ok">✔</span>
                    <span>Done. Employee onboarding pipeline finalized.</span>
                </div>
            `,
            preview: `
                <div class="payslip-preview">
                    <div style="display:flex; align-items:center; gap:12px; margin-bottom:16px; border-bottom: 1px solid rgba(255,255,255,0.06); padding-bottom:10px;">
                        <div style="width:32px; height:32px; border-radius:50%; background:var(--green); color:#0d0f1a; display:flex; align-items:center; justify-content:center; font-weight:700; font-size:0.75rem;">MR</div>
                        <div>
                            <div class="payslip-preview-company" style="font-size:0.75rem; font-family:var(--sans);">Mark Reyes</div>
                            <div style="font-size:0.55rem; color:var(--text-dim); margin-top:2px;">Joined Today · RL-2026-0489</div>
                        </div>
                    </div>
                    <div style="font-size:0.65rem; color:var(--text-dim); display:flex; flex-direction:column; gap:8px;">
                        <div style="display:flex; justify-content:space-between; border-bottom:1px solid rgba(255,255,255,0.04); padding-bottom:4px;"><span style="color:var(--text-dim);">Department</span><span style="color:var(--text);">Engineering</span></div>
                        <div style="display:flex; justify-content:space-between; border-bottom:1px solid rgba(255,255,255,0.04); padding-bottom:4px;"><span style="color:var(--text-dim);">Job Title</span><span style="color:var(--text);">Software Engineer</span></div>
                        <div style="display:flex; justify-content:space-between; border-bottom:1px solid rgba(255,255,255,0.04); padding-bottom:4px;"><span style="color:var(--text-dim);">Work Email</span><span style="color:var(--text);">m.reyes@respawn.ph</span></div>
                        <div style="display:flex; justify-content:space-between; border-bottom:1px solid rgba(255,255,255,0.04); padding-bottom:4px;"><span style="color:var(--text-dim);">IAM Policy</span><span style="color:var(--text); font-family:var(--mono); font-size:0.55rem;">role:engineer-standard</span></div>
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-top:8px;">
                            <span style="color:var(--text-dim);">Onboarding Status</span>
                            <span class="payslip-preview-label" style="color:var(--green); border-color:rgba(0,224,122,0.3); background:rgba(0,224,122,0.05); font-size:0.55rem; padding:1px 6px; border-radius:3px;">COMPLETED</span>
                        </div>
                    </div>
                </div>
            `
        },
        "3": {
            header: "Leave Requests — Sick Leave Approval",
            prompt: "approve leave request #8209 for 'Jaime Cruz' and deduct balance from annual leave pool",
            inputText: "Approve/reject leaves, verify attachments, check balances... ",
            address: "respawn-logics.local/leaves/requests/8209",
            logs: `
                <div class="demo-prompt-box">
                    approve leave request #8209 for 'Jaime Cruz' and deduct balance from annual leave pool
                </div>
                <div class="demo-log-line"><span class="dl-action">Read</span> <span class="dl-file">leave_requests/8209.json</span> <span style="color:var(--text-dim)">·</span> <span style="color:var(--text-dim)">Jaime Cruz</span></div>
                <div class="demo-log-line"><span class="dl-action">Check</span> <span style="color:var(--text);">balance for type: 'Sick Leave' · 12 days available</span></div>
                <div class="demo-log-line"><span class="dl-think">Validating attached medical_certificate.pdf...</span></div>
                <div class="demo-log-line"><span class="dl-ok">✔</span> <span>Verification passed (Signature matches registered provider)</span></div>
                <div class="demo-log-line"><span class="dl-ok">✔</span> <span>Leave Request approved by Line Manager</span></div>
                <div class="demo-file-change">
                    <span class="fc-name">leave_balances/cruz_jaime.json</span>
                    <span><span class="fc-ins">+1</span>&nbsp;<span class="fc-del">-1</span></span>
                </div>
                <div class="demo-log-line demo-done-msg" style="margin-top:4px;">
                    <span class="dl-ok">✔</span>
                    <span>Done. Leave balance updated (9 days remaining). Notification sent.</span>
                </div>
            `,
            preview: `
                <div class="payslip-preview">
                    <div class="payslip-preview-head" style="border-bottom: 1px solid rgba(255,255,255,0.06); padding-bottom: 10px; margin-bottom: 12px;">
                        <div>
                            <div class="payslip-preview-company" style="font-size:0.75rem;">SICK LEAVE REQUEST</div>
                            <div style="font-size:0.55rem; color:var(--text-dim); margin-top:2px;">ID: #8209 · Cruz, Jaime</div>
                        </div>
                        <div class="payslip-preview-label" style="color:var(--green); border-color:rgba(0,224,122,0.3); background:rgba(0,224,122,0.05);">APPROVED</div>
                    </div>
                    <div style="font-size:0.65rem; color:var(--text-dim); display:flex; flex-direction:column; gap:10px; margin-top:8px;">
                        <div>
                            <div style="color:var(--text-dim); margin-bottom:2px;">Requested Period</div>
                            <div style="color:var(--text); font-weight:500;">July 14, 2026 &ndash; July 16, 2026</div>
                        </div>
                        <div>
                            <div style="color:var(--text-dim); margin-bottom:2px;">Duration</div>
                            <div style="color:var(--text); font-weight:500;">3 Work Days</div>
                        </div>
                        <div>
                            <div style="color:var(--text-dim); margin-bottom:2px;">Reason / Diagnostics</div>
                            <div style="color:var(--text); font-weight:400; line-height:1.4; font-family:var(--sans);">Acute viral infection. Advised bed rest and medication.</div>
                        </div>
                        <div style="display:flex; align-items:center; gap:6px; color:var(--green); font-size:0.6rem; background:rgba(0,224,122,0.04); padding:6px; border-radius:4px; border:1px solid rgba(0,224,122,0.1); margin-top:4px;">
                            <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22c5.523 0 10-4.477 10-10S17.523 2 12 2 2 6.477 2 12s4.477 10 10 10z"/><path d="m9 12 2 2 4-4"/></svg>
                            Attachment: medical_cert_cruz.pdf (Verified)
                        </div>
                    </div>
                </div>
            `
        },
        "4": {
            header: "Compliance Exports — 1601-C",
            prompt: "export BIR Form 1601-C for July 2026 compensation tax withholding",
            inputText: "Export tax forms, compile reports, submit eFPS XML... ",
            address: "respawn-logics.local/compliance/bir/1601c",
            logs: `
                <div class="demo-prompt-box">
                    export BIR Form 1601-C for July 2026 compensation tax withholding
                </div>
                <div class="demo-log-line"><span class="dl-action">Read</span> <span class="dl-file">payroll_records/july_2026.db</span></div>
                <div class="demo-log-line"><span class="dl-action">Calculate</span> <span style="color:var(--text);">Total compensation tax: ₱2,847,500.00</span></div>
                <div class="demo-log-line"><span class="dl-action">Calculate</span> <span style="color:var(--text);">Tax withheld: ₱315,920.00</span></div>
                <div class="demo-log-line"><span class="dl-think">Formatting XML file according to BIR eFPS 2026 specification schema...</span></div>
                <div class="demo-log-line"><span class="dl-ok">✔</span> <span>Validation constraints check: 0 warnings, 0 errors</span></div>
                <div class="demo-file-change">
                    <span class="fc-name">exports/bir_form_1601c_july2026.xml</span>
                    <span><span class="fc-ins">+1</span>&nbsp;<span class="fc-del">-0</span></span>
                </div>
                <div class="demo-log-line demo-done-msg" style="margin-top:4px;">
                    <span class="dl-ok">✔</span>
                    <span>Done. Form generated and validated. Ready for sign-off.</span>
                </div>
            `,
            preview: `
                <div class="payslip-preview">
                    <div class="payslip-preview-head" style="border-bottom: 1px solid rgba(255,255,255,0.06); padding-bottom: 10px; margin-bottom: 12px;">
                        <div>
                            <div class="payslip-preview-company" style="font-size:0.7rem; font-family:var(--sans);">BIR Form No. 1601-C</div>
                            <div style="font-size:0.55rem; color:var(--text-dim); margin-top:2px;">Monthly Remittance of Taxes Withheld</div>
                        </div>
                        <div class="payslip-preview-label" style="color: var(--amber); border-color: rgba(251,191,36,0.3); background: rgba(251,191,36,0.05);">PENDING SIGN</div>
                    </div>
                    <div style="font-size:0.62rem; color:var(--text-dim); display:flex; flex-direction:column; gap:8px;">
                        <div style="display:flex; justify-content:space-between; border-bottom:1px solid rgba(255,255,255,0.04); padding-bottom:4px;"><span style="color:var(--text-dim);">For the Month</span><span style="color:var(--text);">July 2026</span></div>
                        <div style="display:flex; justify-content:space-between; border-bottom:1px solid rgba(255,255,255,0.04); padding-bottom:4px;"><span style="color:var(--text-dim);">Total Taxable Compensation</span><span style="color:var(--text);">₱ 2,847,500.00</span></div>
                        <div style="display:flex; justify-content:space-between; border-bottom:1px solid rgba(255,255,255,0.04); padding-bottom:4px;"><span style="color:var(--text-dim);">Total Tax Withheld</span><span style="color:var(--text);">₱ 315,920.00</span></div>
                        <div style="display:flex; justify-content:space-between; border-bottom:1px solid rgba(255,255,255,0.04); padding-bottom:4px;"><span style="color:var(--text-dim);">Penalties / Interest</span><span style="color:var(--text);">₱ 0.00</span></div>
                        <div class="payslip-total" style="border-top:1px solid rgba(255,255,255,0.06); padding-top:6px; margin-top:6px;">
                            <span class="pt-key">REMITTANCE DUE</span>
                            <span class="pt-val" style="color:var(--amber);">₱ 315,920.00</span>
                        </div>
                    </div>
                </div>
            `
        }
    };

    sidebarItems.forEach(item => {
        item.addEventListener('click', function() {
            sidebarItems.forEach(i => i.classList.remove('active'));
            this.classList.add('active');

            const idx = this.getAttribute('data-index');
            const data = demoData[idx];
            if (!data) return;

            // Update content headers, addresses
            centerHeader.textContent = data.header;
            previewAddress.textContent = data.address;

            // Re-render bodies
            centerBody.innerHTML = data.logs;
            previewBody.innerHTML = data.preview;

            // Update the input placeholder prompt
            centerInputText.innerHTML = `${data.inputText}<span class="demo-cursor"></span>`;
        });
    });
})();

</script>
</div>
<div class="fixed-timeline-node" id="scroll-dot"></div>
<script>
window.addEventListener('scroll', () => {
    const dot = document.getElementById('scroll-dot');
    if (!dot || getComputedStyle(dot).display === 'none') return;
    const sections = document.querySelectorAll('.timeline-section');
    const centerY = window.innerHeight / 2;
    let activeSection = null;
    sections.forEach(sec => {
        const rect = sec.getBoundingClientRect();
        if (rect.top <= centerY && rect.bottom >= centerY) {
            activeSection = sec;
        }
    });
    if (activeSection) {
        if (activeSection.id === 'scale') {
            dot.style.background = 'var(--amber)';
            dot.style.boxShadow = '0 0 12px var(--amber)';
        } else if (activeSection.id === 'observability') {
            dot.style.background = 'var(--blue)';
            dot.style.boxShadow = '0 0 12px var(--blue)';
        } else {
            dot.style.background = 'var(--green)';
            dot.style.boxShadow = '0 0 12px var(--green)';
        }
    }
});
</script>
</body>
</html>
