<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Guía de Configuración - Ánima Bot</title>
    <link rel="stylesheet" href="{{ asset('/css/bitrix/setup.css') }}">
    <style>
        /* Solo se aplica si no está ya en setup.css */
        .carousel-container {
            position: relative;
            width: 100%;
            max-width: 600px; /* Limita el ancho */
            margin: 20px auto; /* Centra horizontalmente */
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            overflow: visible;
        }

        .carousel-wrapper {
            display: flex;
            transition: transform 0.3s ease;
            scroll-behavior: smooth;
            overflow-x: auto;
            scroll-snap-type: x mandatory;
        }

        .carousel-slide {
            min-width: 100%;
            scroll-snap-align: start;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .carousel-slide img {
            max-width: 80%;
            max-height: 350px;
            height: auto;
            border-radius: 8px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            object-fit: contain;
        }

        .carousel-controls {
            display: flex;
            justify-content: space-between;
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            width: 100%;
            padding: 0 20px; 
            pointer-events: none;
            z-index: 10; 
        }

        .carousel-btn {
            background: rgba(102, 126, 234, 0.9);
            color: white;
            border: none;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            cursor: pointer;
            font-size: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
            pointer-events: all;
            z-index: 11; 
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.2); 
        }

        .carousel-btn:hover {
            background: rgba(102, 126, 234, 1);
            transform: scale(1.1);
        }
    </style>
</head>
<body>
    <div class="language-selector">
        <button class="lang-btn active" onclick="changeLanguage('es')">Español</button>
        <span style="color: #d1d5db;">|</span>
        <button class="lang-btn" onclick="changeLanguage('en')">English</button>
    </div>

    <div class="container">
        <div class="header">
            <div class="logo" style="background: url('{{ asset('img/icon.png') }}') no-repeat center center; background-size: contain;"></div>
            <h1 data-lang="guide">Guía de Configuración - Ánima Bot</h1>
        </div>

        <!-- Paso 1 -->
        <div class="screen active" id="step1">
            <div class="instructions">
                <h3 data-lang="webhook_instructions">📋 Crear Webhook de salida en Bitrix24</h3>
                <ol>
                    <li data-lang="step1_1">Ingresa a tu portal de Bitrix24</li>
                    <li data-lang="step1_2">Ve a <strong>Recursos para desarrolladores</strong></li>
                    <li data-lang="step1_3">Selecciona <strong>Otro → Webhook de Salida</strong></li>
                    <li><span data-lang="step1_4">URL de tu controlador:</span>
                        <code>https://test-bitrix.anima.bot/api/v1.0.0/webhook/bitrix/message</code>
                    </li>
                    <li data-lang="step1_5">El token de aplicación se generará automáticamente. Cópialo tras guardar el Webhook.</li>
                    <li data-lang="step1_6">Selecciona eventos ONIMCONNECTORMESSAGEADD y ONIMBOTMESSAGEADD</li>
                </ol>

                <!-- Una sola imagen (sin carousel) -->
                <div style="text-align: center; margin-top: 20px;">
                    <img
                        src="{{ asset('img/ConfWebHook.png') }}"
                        alt="Paso Webhook 1"
                        style="
                            max-width: 550px;
                            width: 100%;
                            height: auto;
                            border-radius: 8px;
                            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
                        "
                    >
                </div>
            </div>
        </div>

        <!-- Paso 2 -->
        <div class="screen" id="step2">
            <div class="instructions">
                <h3 data-lang="configure_data">⚙️ Completa los siguientes datos</h3>
                <ul>
                    <li><strong>Token de Autorización:</strong> *******</li>
                    <li><strong>Hash del webhook:</strong> *******</li>
                    <li><strong>Nombre del bot:</strong> *******</li>
                </ul>
            </div>
        </div>

        <!-- Paso 3 -->
        <div class="screen" id="step3">
            <div class="instructions">
                <h3 data-lang="assign_channels">📡 Asignar el bot a canales Open Line</h3>
                <p data-lang="channel_tip">
                    Ve a <strong>Centro de Contacto → Canales</strong> y selecciona el canal donde deseas usar el bot (WhatsApp, Telegram, etc.).
                    Luego asigna el bot instalado “Anima Bot” en la sección <strong>Chat bots</strong>.
                </p>
            </div>

            <!-- Carousel funcional -->
            <div class="carousel-container">
                <div class="carousel-wrapper" id="carouselWrapperStep3">
                    @for ($i = 1; $i <= 11; $i++)
                        <div class="carousel-slide">
                            <img src="{{ asset("img/Bot{$i}.png") }}" alt="Paso Bot {{ $i }}">
                        </div>
                    @endfor
                </div>
                <div class="carousel-controls">
                    <button class="carousel-btn" onclick="previousSlide('carouselWrapperStep3')">‹</button>
                    <button class="carousel-btn" onclick="nextSlide('carouselWrapperStep3')">›</button>
                </div>
            </div>
        </div>

        <div class="footer">
            <p>© 2025 Ánima Bot - PREVI SRL</p>
        </div>
    </div>

    <script>
        let currentLanguage = 'es';
        const translations = {
            es: {
                guide: 'Guía de Configuración - Ánima Bot',
                webhook_instructions: '📋 Crear Webhook de salida en Bitrix24',
                step1_1: 'Ingresa a tu portal de Bitrix24',
                step1_2: 'Ve a Recursos para desarrolladores',
                step1_3: 'Selecciona Otro → Webhook de Salida',
                step1_4: 'URL de tu controlador:',
                step1_5: 'El token de aplicación se generará automáticamente. Cópialo tras guardar el Webhook.',
                step1_6: 'Selecciona eventos ONIMCONNECTORMESSAGEADD y ONIMBOTMESSAGEADD',
                configure_data: '⚙️ Completa los siguientes datos',
                assign_channels: '📡 Asignar el bot a canales Open Line',
                channel_tip: 'Ve a Centro de Contacto → Canales y selecciona el canal donde deseas usar el bot (WhatsApp, Telegram, etc.). Luego asigna el bot instalado “Anima Bot” en la sección Chat bots.'
            },
            en: {
                guide: 'Setup Guide - Anima Bot',
                webhook_instructions: '📋 Create outbound Webhook in Bitrix24',
                step1_1: 'Log into your Bitrix24 portal',
                step1_2: 'Go to Developer Resources',
                step1_3: 'Select Other → Outbound Webhook',
                step1_4: 'Your controller URL:',
                step1_5: 'The application token will be generated automatically. Copy it after saving the Webhook.',
                step1_6: 'Select events ONIMCONNECTORMESSAGEADD and ONIMBOTMESSAGEADD',
                configure_data: '⚙️ Fill in the following data',
                assign_channels: '📡 Assign bot to Open Line channels',
                channel_tip: 'Go to Contact Center → Channels, select the desired one (WhatsApp, Telegram, etc.). Then assign “Anima Bot” in the Chat bots section.'
            }
        };

        function changeLanguage(lang) {
            currentLanguage = lang;
            document.querySelectorAll('.lang-btn').forEach(btn => {
                btn.classList.toggle('active', btn.textContent.toLowerCase().includes(lang === 'es' ? 'español' : 'english'));
            });
            updateTranslations();
        }

        function updateTranslations() {
            const t = translations[currentLanguage];
            document.querySelectorAll('[data-lang]').forEach(el => {
                const key = el.getAttribute('data-lang');
                if (t[key]) el.textContent = t[key];
            });
        }

        function nextSlide(wrapperId) {
            const wrapper = document.getElementById(wrapperId);
            const slideWidth = wrapper.children[0].offsetWidth;
            wrapper.scrollLeft += slideWidth;
        }

        function previousSlide(wrapperId) {
            const wrapper = document.getElementById(wrapperId);
            const slideWidth = wrapper.children[0].offsetWidth;
            wrapper.scrollLeft -= slideWidth;
        }

        updateTranslations();
    </script>
</body>
</html>
