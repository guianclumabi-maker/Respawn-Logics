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
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
            background-color: transparent;
            font-family: var(--sans);
            color: var(--text);
            overflow-x: hidden;
            line-height: 1.5;
            -webkit-font-smoothing: antialiased;
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
            color: #fff;
        }

        .logo-mark {
            width: 32px;
            height: 32px;
            background: linear-gradient(135deg, var(--green), #00b8ff);
            border-radius: 7px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #000;
            font-size: 0.875rem;
        }

        .version-pill {
            font-family: var(--mono);
            font-size: 0.625rem;
            font-weight: 700;
            letter-spacing: 0.1em;
            color: var(--green);
            background: rgba(0,224,122,0.07);
            border: 1px solid rgba(0,224,122,0.18);
            padding: 2px 7px;
            border-radius: 3px;
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

        .nav-links a:hover { color: #fff; }

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

        /* ─── HERO ─── */
        .hero {
            min-height: 85vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
            padding: 140px 24px 80px;
            position: relative;
            overflow: hidden;
        }

        /* Layered background — navy base with dynamic drifting grid */
        .global-bg {
            position: fixed;
            inset: -100px;
            z-index: 0;
            pointer-events: none;
            background-color: var(--bg);
            background-image:
                radial-gradient(ellipse 80% 60% at 50% 0%, rgba(0,184,255,0.07) 0%, transparent 70%),
                radial-gradient(ellipse 70% 60% at 80% 100%, rgba(155,109,255,0.06) 0%, transparent 70%),
                radial-gradient(ellipse 50% 50% at 20% 50%, rgba(0,224,122,0.05) 0%, transparent 60%);
        }

        .global-grid {
            position: fixed;
            inset: -100px;
            z-index: 0;
            pointer-events: none;
            background-image:
                linear-gradient(rgba(0, 224, 122, 0.05) 1px, transparent 1px),
                linear-gradient(90deg, rgba(0, 224, 122, 0.05) 1px, transparent 1px);
            background-size: 60px 60px;
            animation: grid-drift 20s linear infinite;
        }

        .app-wrapper {
            position: relative;
            z-index: 1;
        }

        @keyframes grid-drift {
            0% { transform: translate(0, 0); }
            100% { transform: translate(-60px, -60px); }
        }

        .hero-badge {
            position: relative;
            z-index: 2;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-family: var(--mono);
            font-size: 0.6875rem;
            font-weight: 600;
            letter-spacing: 0.1em;
            color: var(--green);
            border: 1px solid rgba(0,224,122,0.22);
            background: rgba(0,224,122,0.05);
            padding: 6px 16px;
            border-radius: 4px;
            margin-bottom: 36px;
        }

        .ping {
            width: 7px; height: 7px;
            border-radius: 50%;
            background: var(--green);
            animation: ping 2s ease infinite;
        }

        @keyframes ping {
            0%, 100% { opacity: 1; box-shadow: 0 0 0 0 rgba(0,224,122,0.5); }
            50% { opacity: 0.6; box-shadow: 0 0 0 4px rgba(0,224,122,0); }
        }

        .hero-h1 {
            position: relative;
            z-index: 2;
            font-size: clamp(3rem, 7.5vw, 5.75rem);
            font-weight: 700;
            line-height: 1.0;
            letter-spacing: -0.04em;
            color: #fff;
            max-width: 880px;
            margin-bottom: 28px;
        }

        .hero-h1 .accent {
            background: linear-gradient(90deg, var(--green), #00c9ff);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            position: relative;
        }

        .hero-h1 .accent::after {
            content: '_';
            -webkit-text-fill-color: var(--green);
            animation: blink 1.1s step-start infinite;
        }

        @keyframes blink {
            0%, 100% { opacity: 1; }
            50% { opacity: 0; }
        }

        .hero-sub {
            position: relative;
            z-index: 2;
            font-size: 1.1rem;
            color: var(--text-mid);
            max-width: 560px;
            line-height: 1.75;
            margin-bottom: 48px;
            font-weight: 400;
        }

        .hero-actions {
            position: relative;
            z-index: 2;
            display: flex;
            gap: 12px;
            align-items: center;
            margin-bottom: 80px;
            flex-wrap: wrap;
            justify-content: center;
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
            color: #fff;
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
            max-width: 1180px;
            margin: 0 auto;
            padding: 110px 24px;
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
            color: #fff;
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
            color: #fff;
        }

        /* MODULE CARDS GRID */
        .module-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 14px;
        }

        .mod-card {
            background: var(--bg2);
            border: 1px solid var(--border2);
            border-radius: 10px;
            padding: 26px 28px;
            position: relative;
            overflow: hidden;
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
            color: #fff;
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
            border-top: 1px solid var(--border3);
            margin: 0;
        }

        /* ─── PHILOSOPHY SECTION ─── */
        .story-section {
            background: var(--bg2);
            border-top: 1px solid var(--border3);
            border-bottom: 1px solid var(--border3);
        }

        .story-inner {
            max-width: 1180px;
            margin: 0 auto;
            padding: 110px 24px;
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
        .t-c { color: #fff; }
        .t-o { color: var(--text-dim); padding-left: 18px; font-size: 0.75rem; }
        .t-cm { color: #354050; }
        .t-v { color: var(--amber); }
        .t-gap { height: 0.4rem; }
        .t-cursor { display: inline-block; width: 8px; height: 13px; background: var(--green); animation: blink 1.1s step-start infinite; vertical-align: middle; }

        /* ─── BETA ─── */
        .beta-wrap {
            max-width: 1180px;
            margin: 0 auto;
            padding: 110px 24px;
        }

        .beta-card {
            background: var(--bg2);
            border: 1px solid var(--border2);
            border-radius: 14px;
            padding: 64px 60px;
            display: grid;
            grid-template-columns: 1fr 240px;
            gap: 60px;
            align-items: center;
            position: relative;
            overflow: hidden;
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
            color: #fff;
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

        /* ─── FOOTER ─── */
        footer {
            border-top: 1px solid var(--border2);
        }

        .footer-inner {
            max-width: 1180px;
            margin: 0 auto;
            padding: 36px 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
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
            font-size: 1rem;
            transition: color 0.2s;
        }

        .footer-socials a:hover { color: #fff; }

        /* ─── RESPONSIVE ─── */
        @media (max-width: 960px) {
            nav { padding: 0 20px; }
            .nav-links a:not(.nav-cta) { display: none; }
            .hero { padding: 110px 20px 70px; }
            .hud { grid-template-columns: repeat(2, 1fr); }
            .hud-item:nth-child(2) { border-right: none; }
            .module-grid { grid-template-columns: 1fr 1fr; }
            .mod-card.wide { grid-column: span 2; }
            .story-inner { grid-template-columns: 1fr; gap: 40px; }
            .beta-card { grid-template-columns: 1fr; padding: 36px 28px; }
            .perks { grid-template-columns: 1fr; }
            .price-panel { text-align: left; }
            .footer-inner { flex-direction: column; gap: 16px; text-align: center; }
        }

        @media (max-width: 600px) {
            .module-grid { grid-template-columns: 1fr; }
            .mod-card.wide { grid-column: span 1; }
            .hero-h1 { font-size: 2.75rem; }
            .hud { grid-template-columns: 1fr 1fr; }
        }
        /* ─── NEW STORY SECTIONS ─── */
        .story-section {
            padding: 60px 24px;
            width: 100%;
            text-align: center;
            position: relative;
        }
        .story-container {
            max-width: 1200px;
            margin: 0 auto;
            position: relative;
            background: rgba(10, 15, 24, 0.7);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border: 1px solid rgba(255, 255, 255, 0.05);
            border-radius: 24px;
            padding: 80px 40px;
            box-shadow: 0 30px 60px rgba(0, 0, 0, 0.4);
        }
        @media (max-width: 768px) {
            .story-container { padding: 40px 20px; }
        }
        .story-section h2 {
            font-size: clamp(2rem, 5vw, 3.25rem);
            color: #fff;
            margin-bottom: 24px;
            line-height: 1.15;
            letter-spacing: -0.02em;
        }
        .story-section p.sub {
            font-size: 1.125rem;
            color: var(--text-mid);
            max-width: 700px;
            margin: 0 auto 64px;
            line-height: 1.6;
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
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 24px;
            margin-bottom: 56px;
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
            color: #fff;
            font-weight: 600;
            max-width: 800px;
            margin: 0 auto;
        }

        /* 4 Pillars */
        .pillars-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 24px;
        }
        .pillar-card {
            background: var(--bg2);
            border: 1px solid var(--border2);
            border-radius: 12px;
            padding: 32px 24px;
            text-align: left;
        }
        .pillar-card h3 {
            font-size: 1.25rem;
            color: #fff;
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
            background: var(--bg3);
            border: 1px solid var(--border2);
            border-radius: 12px;
            padding: 40px;
            text-align: left;
            transition: transform 0.2s;
        }
        .why-card:hover { transform: translateY(-3px); border-color: rgba(0,224,122,0.3); }
        .why-card i {
            font-size: 2rem;
            color: var(--green);
            margin-bottom: 20px;
        }
        .why-card h3 {
            font-size: 1.375rem;
            color: #fff;
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

        /* Gaming CTA */
        .gaming-cta {
            padding: 60px 24px;
            width: 100%;
            text-align: center;
            position: relative;
        }
        .gaming-cta .story-container {
            background: linear-gradient(135deg, rgba(10, 15, 24, 0.8), rgba(0, 224, 122, 0.05));
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
            color: #fff;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .gaming-step i { color: var(--green); }
        .gaming-divider { color: var(--text-dim); }
    </style>
</head>
<body>
    <div class="global-bg"></div>
    <div class="global-grid"></div>

<div class="app-wrapper">
<!-- NAV -->
<nav>
    <?= renderLogo('navbar') ?>
    <div class="nav-links">
        <a href="#overview">Platform</a>
        <a href="deep_dive.php">Deep Dive</a>
        <a href="#why">Why Us</a>
        <a href="#story">The Story</a>
        <a href="#beta">Beta</a>
        <?php if ($loggedIn): ?>
            <a href="<?= url('/pages/dashboard.php') ?>" class="nav-cta">[ RESUME ]</a>
        <?php else: ?>
            <a href="login.php" class="nav-cta">[ LOGIN ]</a>
        <?php endif; ?>
    </div>
</nav>

<!-- HERO -->
<section class="hero" id="home">

    <div class="hero-badge">
        <div class="ping"></div>
        BETA SERVERS ONLINE &nbsp;·&nbsp; RESPAWN-PH
    </div>

    <h1 class="hero-h1">
        Your team deserves more<br>than a spreadsheet.<br>Time to <span class="accent">level up</span>
    </h1>

    <p class="hero-sub">
        Respawn Logics is the enterprise HR platform built by people who actually played video games growing up. Payroll, hiring, performance, attendance — wired together and secured properly.
    </p>

    <div class="hero-actions">
        <?php if ($loggedIn): ?>
            <a href="<?= url('/pages/dashboard.php') ?>" class="btn-primary">
                <i class="fa-solid fa-play"></i> Resume Session
            </a>
        <?php else: ?>
            <a href="<?= url('/onboarding/') ?>" class="btn-primary">
                <i class="fa-solid fa-play"></i> Initialize Setup
            </a>
            <a href="<?= url('/login.php') ?>" class="btn-ghost">
                <i class="fa-solid fa-floppy-disk"></i> Continue Save File
            </a>
        <?php endif; ?>
    </div>

    <div class="hud">
        <div class="hud-item">
            <div class="hud-key">// MODULES</div>
            <div class="hud-val">17</div>
            <div class="hud-desc">Fully wired modules</div>
        </div>
        <div class="hud-item">
            <div class="hud-key">// UPTIME</div>
            <div class="hud-val">99.9%</div>
            <div class="hud-desc">No unplanned outages</div>
        </div>
        <div class="hud-item">
            <div class="hud-key">// BUILD</div>
            <div class="hud-val amber">BETA</div>
            <div class="hud-desc">Free for early partners</div>
        </div>
        <div class="hud-item">
            <div class="hud-key">// STACK</div>
            <div class="hud-val blue" style="font-size:1rem; padding-top:6px;">PHP · MySQL · React</div>
            <div class="hud-desc">No black boxes</div>
        </div>
    </div>
</section>

<!-- SECTION 1: JOURNEY -->
<section class="story-section" id="journey">
    <div class="story-container">
        <h2>Manage every stage of the employee journey</h2>
        <p class="sub">From recruitment and onboarding to payroll, performance, and employee support, Respawn Logic helps organizations manage their workforce from a single platform.</p>
        
        <div class="journey-flow">
            <div class="journey-node"><i class="fa-solid fa-crosshairs"></i> Recruit</div> <i class="fa-solid fa-chevron-right journey-arrow"></i>
            <div class="journey-node"><i class="fa-solid fa-handshake"></i> Hire</div> <i class="fa-solid fa-chevron-right journey-arrow"></i>
            <div class="journey-node"><i class="fa-solid fa-bolt"></i> Onboard</div> <i class="fa-solid fa-chevron-right journey-arrow"></i>
            <div class="journey-node"><i class="fa-solid fa-briefcase"></i> Work</div> <i class="fa-solid fa-chevron-right journey-arrow"></i>
            <div class="journey-node"><i class="fa-solid fa-coins"></i> Pay</div> <i class="fa-solid fa-chevron-right journey-arrow"></i>
            <div class="journey-node"><i class="fa-solid fa-ranking-star"></i> Develop</div> <i class="fa-solid fa-chevron-right journey-arrow"></i>
            <div class="journey-node"><i class="fa-solid fa-shield-heart"></i> Support</div> <i class="fa-solid fa-chevron-right journey-arrow"></i>
            <div class="journey-node" style="background: rgba(155,109,255,0.1); border-color: var(--purple); color: var(--purple);"><i class="fa-solid fa-level-up-alt"></i> Grow</div>
        </div>
    </div>
</section>

<!-- SECTION 2: PROBLEMS ELIMINATED -->
<section class="story-section" id="problems">
    <div class="story-container">
        <h2>Stop managing people across disconnected tools</h2>
        
        <div class="problems-grid">
            <div class="problem-card"><i class="fa-solid fa-skull"></i> Spreadsheets</div>
            <div class="problem-card"><i class="fa-solid fa-hourglass-half"></i> Manual Tracking</div>
            <div class="problem-card"><i class="fa-solid fa-envelope-circle-xmark"></i> Email Approvals</div>
            <div class="problem-card"><i class="fa-solid fa-triangle-exclamation"></i> Payroll Rework</div>
            <div class="problem-card"><i class="fa-solid fa-ghost"></i> Scattered Documents</div>
            <div class="problem-card"><i class="fa-solid fa-plug-circle-xmark"></i> Disconnected Records</div>
        </div>

        <div class="solution-block">
            Respawn Logic brings your people data, workflows, and processes into one place.
        </div>
    </div>
</section>

<!-- SECTION 3: PLATFORM OVERVIEW (4 PILLARS) -->
<section class="story-section" id="overview">
    <div class="story-container">
        <h2>Everything you need. Nothing you don't.</h2>
        <p class="sub">A unified platform built on four core pillars.</p>

        <div class="pillars-grid">
            <div class="pillar-card">
                <h3><i class="fa-solid fa-users-viewfinder"></i> Workforce</h3>
                <ul>
                    <li>Attendance</li>
                    <li>Leave Management</li>
                    <li>Scheduling</li>
                    <li>Organization Structure</li>
                </ul>
            </div>
            <div class="pillar-card">
                <h3><i class="fa-solid fa-coins"></i> Pay & Benefits</h3>
                <ul>
                    <li>Enterprise Payroll</li>
                    <li>Government Contributions</li>
                    <li>Benefits Administration</li>
                    <li>Expense Management</li>
                </ul>
            </div>
            <div class="pillar-card">
                <h3><i class="fa-solid fa-star"></i> Talent & Growth</h3>
                <ul>
                    <li>Recruitment / ATS</li>
                    <li>Automated Onboarding</li>
                    <li>Performance Reviews</li>
                    <li>Succession Planning</li>
                </ul>
            </div>
            <div class="pillar-card">
                <h3><i class="fa-solid fa-headset"></i> Employee Support</h3>
                <ul>
                    <li>Employee Relations</li>
                    <li>Knowledge Base</li>
                    <li>Case Management</li>
                    <li>AI Companion</li>
                </ul>
            </div>
        </div>
    </div>
</section>

<!-- SECTION 4: WHY TEAMS CHOOSE RESPAWN -->
<section class="story-section" id="why">
    <div class="story-container">
        <h2>Built for real-world people operations</h2>
        <p class="sub">Why organizations are leaving legacy tools behind.</p>

        <div class="why-grid">
            <div class="why-card">
                <i class="fa-solid fa-gamepad"></i>
                <h3>One Platform</h3>
                <p>Manage employee data, payroll, performance, and employee support from a unified system without messy integrations.</p>
            </div>
            <div class="why-card">
                <i class="fa-solid fa-microchip"></i>
                <h3>Configurable Workflows</h3>
                <p>Adapt processes to your organization's needs. Build approval chains, document requirements, and custom fields.</p>
            </div>
            <div class="why-card">
                <i class="fa-solid fa-bolt"></i>
                <h3>Employee Self-Service</h3>
                <p>Give employees access to the information and tools they need to request leave, check payslips, and log attendance.</p>
            </div>
            <div class="why-card">
                <i class="fa-solid fa-book-journal-whills"></i>
                <h3>Knowledge-Driven</h3>
                <p>Policies, procedures, and guidance available when your teams need them. Integrated directly into the support flow.</p>
            </div>
        </div>
    </div>
</section>

<!-- SECTION 5: EMPLOYEE EXPERIENCE (COMPANION) -->
<section class="story-section" id="experience">
    <div class="story-container split-section">
        <div>
            <h2 style="font-size: clamp(1.8rem, 4vw, 2.5rem);">Help employees find answers faster.</h2>
            <p style="font-size: 1.125rem; color: var(--text-mid); line-height: 1.6; margin-bottom: 30px;">
                Employees can access leave balances, attendance records, payroll information, company policies, and more from a single, intelligent experience.
            </p>
            <ul style="list-style: none; color: var(--text); font-size: 1rem; display: flex; flex-direction: column; gap: 16px;">
                <li><i class="fa-solid fa-check" style="color: var(--green); margin-right: 12px;"></i> Instant policy lookups</li>
                <li><i class="fa-solid fa-check" style="color: var(--green); margin-right: 12px;"></i> Self-service document generation</li>
                <li><i class="fa-solid fa-check" style="color: var(--green); margin-right: 12px;"></i> 24/7 autonomous support</li>
            </ul>
        </div>
        <div class="split-image">
            <img src="https://images.unsplash.com/photo-1618761714954-0b8cd0026356?auto=format&fit=crop&w=800&q=80" alt="Platform Dashboard">
        </div>
    </div>
</section>

<!-- SECTION 6: GAMING CTA -->
<section class="gaming-cta">
    <div class="story-container">
        <h2 style="font-size: 2.5rem; color: #fff; margin-bottom: 20px;">A Better Way to Manage People</h2>
        <p style="color: var(--text-mid); font-size: 1.125rem; max-width: 600px; margin: 0 auto;">Equip your HR team with the ultimate loadout. No more juggling ten different tabs just to onboard a single employee or process payroll.</p>
        
        <div class="gaming-flow">
            <div class="gaming-step"><i class="fa-solid fa-location-dot"></i> Spawn Point</div>
            <i class="fa-solid fa-chevron-right gaming-divider"></i>
            <div class="gaming-step"><i class="fa-solid fa-users"></i> Build Your Team</div>
            <i class="fa-solid fa-chevron-right gaming-divider"></i>
            <div class="gaming-step"><i class="fa-solid fa-arrow-trend-up"></i> Level Up Performance</div>
            <i class="fa-solid fa-chevron-right gaming-divider"></i>
            <div class="gaming-step"><i class="fa-solid fa-shield-heart"></i> Support Your People</div>
        </div>
    </div>
</section>

<hr class="divider">

<!-- STORY -->
<div class="story-section" id="story">
    <div class="story-container story-inner">
        <div>
            <div class="eyebrow">// THE LORE</div>
            <h2 class="section-h" style="margin-bottom: 24px;">Built by someone who plays games and hates boring HR software.</h2>
            <p style="font-size: 1rem; color: var(--text-mid); line-height: 1.8; margin-bottom: 20px; text-align: left;">
                In every game, dying isn't the end. You <strong style="color:#fff">respawn</strong>. You come back smarter, better equipped, with another shot at the objective. That's the mindset we think every company should have toward its people — second chances, continuous growth, and the belief that your team can always level up.
            </p>
            <p style="font-size: 1rem; color: var(--text-mid); line-height: 1.8; margin-bottom: 20px; text-align: left;">
                The <strong style="color:#fff">Logics</strong> half keeps us grounded. This isn't a game — people's livelihoods depend on accurate payroll, fair reviews, and secure personal data. We bring the energy of gaming culture with the discipline of enterprise software.
            </p>
            <p style="font-size: 1rem; color: var(--text); line-height: 1.8; font-weight: 500; text-align: left;">
                Built in the Philippines 🇵🇭, for companies that take their people seriously — without taking themselves too seriously.
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

<section class="beta-wrap" id="beta">
    <div style="display: grid; gap: 32px; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); align-items: stretch;">
        
        <!-- SOLO PLAN -->
        <div class="beta-card" style="grid-template-columns: 1fr; gap: 20px; padding: 48px; position: relative;">
            <div class="beta-label" style="background: rgba(0, 224, 122, 0.07); color: var(--green); border-color: rgba(0, 224, 122, 0.2);"><i class="fa-solid fa-user-astronaut"></i> SOLO FOUNDER</div>
            <h2 class="beta-h" style="font-size: 2rem;">Build your empire.</h2>
            <p class="beta-p" style="max-width: 100%; margin-bottom: 24px;">Perfect for solo developers, indie hackers, and single-member startups who need an enterprise-grade HRIS to start right.</p>
            
            <div class="perks" style="grid-template-columns: 1fr; margin-bottom: 32px; gap: 12px;">
                <div class="perk"><i class="fa-solid fa-circle-check"></i> 1 Sandbox Environment</div>
                <div class="perk"><i class="fa-solid fa-circle-check"></i> 1 Administrator Seat</div>
                <div class="perk"><i class="fa-solid fa-circle-check"></i> All Core Modules included</div>
                <div class="perk"><i class="fa-solid fa-circle-check"></i> Community Support</div>
            </div>
            
            <div class="price-panel" style="text-align: left; margin-top: auto;">
                <div class="price-num">₱0</div>
                <div class="price-tag">FOREVER FREE</div>
                <?php if ($loggedIn): ?>
                    <a href="<?= url('/pages/dashboard.php') ?>" class="btn-primary" style="width:100%; justify-content:center;">
                        <i class="fa-solid fa-play"></i> Go to Dashboard
                    </a>
                <?php else: ?>
                    <a href="<?= url('/register.php') ?>" class="btn-primary" style="width:100%; justify-content:center;">
                        <i class="fa-solid fa-rocket"></i> Create Solo Workspace
                    </a>
                    <a href="<?= url('/login.php') ?>" class="btn-ghost" style="width:100%; justify-content:center; margin-top: 10px;">
                        Sign In
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <!-- ENTERPRISE BETA PLAN -->
        <div class="beta-card" style="grid-template-columns: 1fr; gap: 20px; padding: 48px; position: relative;">
            <div class="beta-label"><i class="fa-solid fa-flask"></i> PRIVATE BETA — LIMITED SLOTS</div>
            <h2 class="beta-h" style="font-size: 2rem;">Join before we<br>go public.</h2>
            <p class="beta-p" style="max-width: 100%; margin-bottom: 24px;">We're onboarding select enterprise partners. Every feature completely free while we battle-test the platform together.</p>
            
            <div class="perks" style="grid-template-columns: 1fr; margin-bottom: 32px; gap: 12px;">
                <div class="perk"><i class="fa-solid fa-circle-check"></i> Unlimited employee seats</div>
                <div class="perk"><i class="fa-solid fa-circle-check"></i> Batch structure onboarding</div>
                <div class="perk"><i class="fa-solid fa-circle-check"></i> Direct line to the dev team</div>
                <div class="perk"><i class="fa-solid fa-circle-check"></i> Priority onboarding support</div>
            </div>
            
            <div class="price-panel" style="text-align: left; margin-top: auto;">
                <div class="price-num">₱0</div>
                <div class="price-tag">DURING BETA PERIOD</div>
                <?php if ($loggedIn): ?>
                    <a href="<?= url('/pages/dashboard.php') ?>" class="btn-primary" style="width:100%; justify-content:center;">
                        <i class="fa-solid fa-play"></i> Go to Dashboard
                    </a>
                <?php else: ?>
                    <a href="<?= url('/onboarding/') ?>" class="btn-primary" style="width:100%; justify-content:center;">
                        <i class="fa-solid fa-building"></i> Claim Enterprise Slot
                    </a>
                    <a href="<?= url('/login.php') ?>" class="btn-ghost" style="width:100%; justify-content:center; margin-top: 10px;">
                        Sign In
                    </a>
                <?php endif; ?>
            </div>
        </div>

    </div>
</section>

<!-- FOOTER -->
<footer>
    <div class="footer-inner">
        <div class="footer-copy">
            © <?= date('Y') ?> Respawn Logics Inc. &nbsp;·&nbsp; Built in the Philippines 🇵🇭
        </div>
        <div class="footer-socials">
            <a href="#"><i class="fa-brands fa-linkedin"></i></a>
            <a href="#"><i class="fa-brands fa-twitter"></i></a>
            <a href="#"><i class="fa-brands fa-github"></i></a>
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
</script>
</div>
</body>
</html>
