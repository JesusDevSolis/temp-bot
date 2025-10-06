<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Configuración Ánima Bot</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            max-width: 600px;
            width: 100%;
            padding: 40px;
            animation: slideUp 0.5s ease;
        }
        
        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .logo {
            width: 80px;
            height: 80px;
            background-image: url('/img/icon.png');
            background-repeat: no-repeat;
            background-position: center center;
            background-size: contain;
            border-radius: 20px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 20px;
        }
        
        h1 {
            color: #333;
            font-size: 28px;
            margin-bottom: 10px;
        }
        
        /* Language Selector */
        .language-selector {
            position: absolute;
            top: 20px;
            right: 20px;
            display: flex;
            gap: 10px;
            background: white;
            padding: 8px 15px;
            border-radius: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .lang-btn {
            background: none;
            border: none;
            color: #718096;
            cursor: pointer;
            font-size: 14px;
            padding: 5px 10px;
            border-radius: 10px;
            transition: all 0.3s ease;
        }
        
        .lang-btn:hover {
            background: #f8f9fa;
            color: #667eea;
        }
        
        .lang-btn.active {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        /* Progress Bar */
        .progress-container {
            margin-bottom: 30px;
        }
        
        .progress-steps {
            display: flex;
            justify-content: space-between;
            margin-bottom: 20px;
        }
        
        .step {
            flex: 1;
            text-align: center;
            position: relative;
        }
        
        .step::after {
            content: '';
            position: absolute;
            top: 20px;
            left: 50%;
            width: 100%;
            height: 2px;
            background: #e1e8ed;
            z-index: -1;
        }
        
        .step:last-child::after {
            display: none;
        }
        
        .step-number {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #e1e8ed;
            color: #718096;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            margin-bottom: 8px;
            position: relative;
            z-index: 1;
            transition: all 0.3s ease;
        }
        
        .step.active .step-number {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .step.completed .step-number {
            background: #48bb78;
            color: white;
        }
        
        .step.completed .step-number::after {
            content: '✓';
            position: absolute;
            font-size: 16px;
        }
        
        .step.completed .step-number span {
            display: none;
        }
        
        .step-label {
            font-size: 12px;
            color: #718096;
        }
        
        .step.active .step-label {
            color: #667eea;
            font-weight: 600;
        }
        
        /* Screen Container */
        .screen {
            display: none;
            animation: fadeIn 0.3s ease;
        }
        
        .screen.active {
            display: block;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        /* Instructions Screen */
        .instructions {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .instructions h3 {
            color: #333;
            margin-bottom: 15px;
            font-size: 18px;
        }
        
        .instructions ol {
            padding-left: 20px;
        }
        
        .instructions li {
            margin-bottom: 12px;
            color: #555;
            line-height: 1.6;
        }
        
        .instructions code {
            background: #e1e8ed;
            padding: 2px 6px;
            border-radius: 4px;
            font-family: monospace;
            font-size: 13px;
        }
        
        .warning-box {
            background: #fef5e7;
            border: 1px solid #f9c74f;
            border-radius: 8px;
            padding: 12px;
            margin: 15px 0;
        }
        
        .warning-box strong {
            color: #f9844a;
        }
        
        .info-box {
            background: #e6f2ff;
            border: 1px solid #93bbfc;
            border-radius: 8px;
            padding: 12px;
            margin: 15px 0;
        }
        
        .info-box strong {
            color: #2563eb;
        }
        
        /* Form Styles (Screen 2) */
        .portal-info {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 30px;
            border-left: 4px solid #667eea;
        }
        
        .portal-info strong {
            color: #667eea;
        }
        
        .status {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: #d4f4dd;
            color: #2d7a3e;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 14px;
            margin-top: 10px;
        }
        
        .status::before {
            content: '✓';
            display: inline-block;
            width: 18px;
            height: 18px;
            background: #2d7a3e;
            color: white;
            border-radius: 50%;
            text-align: center;
            line-height: 18px;
            font-size: 12px;
        }
        
        .form-group {
            margin-bottom: 25px;
        }
        
        label {
            display: block;
            color: #555;
            font-weight: 600;
            margin-bottom: 8px;
            font-size: 14px;
        }
        
        input[type="text"],
        input[type="password"] {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid #e1e8ed;
            border-radius: 10px;
            font-size: 15px;
            transition: all 0.3s ease;
            background: #fafbfc;
        }
        
        input[type="text"]:focus,
        input[type="password"]:focus {
            outline: none;
            border-color: #667eea;
            background: white;
            box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.1);
        }
        
        input[type="text"]::placeholder,
        input[type="password"]::placeholder {
            color: #a0aec0;
        }
        
        .help-text {
            font-size: 12px;
            color: #718096;
            margin-top: 6px;
            line-height: 1.4;
        }
        
        .section-divider {
            border-bottom: 2px solid #e1e8ed;
            margin: 30px 0;
            position: relative;
        }
        
        .section-divider::after {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 0;
            width: 50px;
            height: 2px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        
        /* Navigation Buttons */
        .navigation {
            display: flex;
            justify-content: space-between;
            gap: 10px;
            margin-top: 30px;
        }
        
        .btn-primary {
            padding: 14px 24px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
            flex: 1;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
        }
        
        .btn-primary:active {
            transform: translateY(0);
        }
        
        .btn-primary:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }
        
        .btn-secondary {
            padding: 14px 24px;
            background: #f8f9fa;
            color: #667eea;
            border: 2px solid #e1e8ed;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            flex: 1;
        }
        
        .btn-secondary:hover {
            background: #e1e8ed;
        }
        
        .btn-success {
            background: linear-gradient(135deg, #48bb78 0%, #38a169 100%);
        }
        
        .alert {
            padding: 12px 16px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: none;
            animation: fadeIn 0.3s ease;
        }
        
        .alert-success {
            background: #d4f4dd;
            color: #2d7a3e;
            border: 1px solid #a8e6b7;
        }
        
        .alert-error {
            background: #feeaea;
            color: #c53030;
            border: 1px solid #fc8181;
        }
        
        .alert-info {
            background: #e6f2ff;
            color: #2563eb;
            border: 1px solid #93bbfc;
        }
        
        .footer {
            text-align: center;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e1e8ed;
            color: #718096;
            font-size: 13px;
        }
        
        .loading {
            display: none;
            text-align: center;
            margin: 20px 0;
        }
        
        .spinner {
            border: 3px solid #f3f3f3;
            border-top: 3px solid #667eea;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
            margin: 0 auto;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        /* Image Carousel Styles */
        .carousel-container {
            position: relative;
            width: 100%;
            margin: 20px 0;
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            overflow: hidden;
        }
        
        .carousel-wrapper {
            display: flex;
            transition: transform 0.3s ease;
        }
        
        .carousel-slide {
            min-width: 100%;
            display: flex;
            justify-content: center;
            align-items: center;
        }
        
        .carousel-slide img {
            max-width: 100%;
            height: auto;
            border-radius: 8px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        
        .carousel-controls {
            display: flex;
            justify-content: space-between;
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            width: 100%;
            padding: 0 10px;
            pointer-events: none;
        }
        
        .carousel-btn {
            background: rgba(102, 126, 234, 0.9);
            color: white;
            border: none;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            transition: all 0.3s ease;
            pointer-events: all;
        }
        
        .carousel-btn:hover {
            background: rgba(102, 126, 234, 1);
            transform: scale(1.1);
        }
        
        .carousel-indicators {
            display: flex;
            justify-content: center;
            gap: 8px;
            margin-top: 15px;
        }
        
        .carousel-dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: #d1d5db;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .carousel-dot.active {
            background: #667eea;
            width: 30px;
            border-radius: 5px;
        }
    </style>
</head>
<body>
    <!-- Language Selector -->
    <div class="language-selector">
        <button class="lang-btn active" onclick="changeLanguage('es')">Español</button>
        <span style="color: #d1d5db;">|</span>
        <button class="lang-btn" onclick="changeLanguage('en')">English</button>
    </div>
    
    <div class="container">
        <div class="header">
            <div class="logo"></div>
            <h1>Configuración Ánima Bot</h1>
        </div>
        
        <!-- Progress Bar -->
        <div class="progress-container">
            <div class="progress-steps">
                <div class="step active" id="step1">
                    <div class="step-number"><span>1</span></div>
                    <div class="step-label">Crear Webhook</div>
                </div>
                <div class="step" id="step2">
                    <div class="step-number"><span>2</span></div>
                    <div class="step-label">Configurar Datos</div>
                </div>
                <div class="step" id="step3">
                    <div class="step-number"><span>3</span></div>
                    <div class="step-label">Asignar Canales</div>
                </div>
            </div>
        </div>
        
        <div id="alertMessage" class="alert"></div>
        
        <!-- Screen 1: Instrucciones Webhook -->
        <div class="screen active" id="screen1">
            <div class="instructions">
                <h3 data-lang="webhook_instructions">📋 Instrucciones para crear el Webhook de Salida</h3>
                <ol id="webhook-steps">
                    <li data-lang="step1_1">Ingresa a tu portal de Bitrix24</li>
                    <li data-lang="step1_2">Ve a <strong>Recursos para desarrollador</strong></li>
                    <li data-lang="step1_3">Selecciona <strong>Otro → Webhook de Salida</strong></li>
                    <li><span data-lang="step1_4">URL de su controlador:</span>
                        <code style="display: block; margin-top: 5px;">https://test-bitrix.anima.bot/api/v1.0.0/webhook/bitrix/message</code>
                    </li>
                    <li data-lang="step1_5"><strong>Token de aplicación:</strong> se genera automáticamente y deberá copiarlo después de guardar el Webhook de salida, será necesario para el siguiente paso</li>
                    <li><span data-lang="step1_6">Eventos:</span>
                        <ul style="margin-top: 8px; margin-left: 20px;">
                            <li>New Message From Open Channel (ONIMCONNECTORMESSAGEADD)</li>
                            <li>New Chat Message (ONIBOTMESSAGEADD)</li>
                        </ul>
                    </li>
                    <li data-lang="step1_7">Guardar</li>
                    <li data-lang="step1_8">Copiar Token de la aplicación y cerrar</li>
                </ol>
            </div>
            
            <div class="warning-box">
                <strong data-lang="important">⚠️ Importante:</strong> <span data-lang="hash_message">El hash único será proporcionado por PREVI SRL. 
                Contacta a soporte para obtenerlo.</span><br><br>
                <span data-lang="hash_support">Puede utilizar el siguiente hash para solicitar soporte con PREVI SRL:</span><br>
                <code style="display: block; margin-top: 8px; word-break: break-all;">
                yJpdiI6IlU4WnU2RklOTHRuaklJQjc4NUppbXc9PSIsInZhbHVlIjoiUVdlNHFxVjhJcWVIbUVoVytOemtWZjdUMGlJSmN4RllGdElXV3ZncjRnRT0iLCJtYWMiOiIwMWVhMzQ3ZGU1NTllOGY2ZTFiMjk4ODYwMGU2OTdkNjk1MmQyNzVmMDQyYWY1OGE4YWVhZjMwNGIxMTdhMjAwIiwidGFnIjoiIn0=
                </code>
            </div>
            
            <div class="info-box">
                <strong data-lang="note">ℹ️ Nota:</strong> <span data-lang="note_message">Necesitarás el token de autorización y el hash para 
                completar el siguiente paso.</span>
            </div>
            
            <!-- Image showing webhook configuration -->
            <div style="margin: 20px 0; text-align: center;">
                <img src="/img/ConfWebHook.png" alt="Configuración del Webhook" 
                    style="max-width: 100%; border-radius: 10px; box-shadow: 0 4px 15px rgba(0,0,0,0.1);">
            </div>
            
            <div class="navigation">
                <button type="button" class="btn-primary" onclick="nextScreen()">
                    <span data-lang="continue">Continuar</span> →
                </button>
            </div>
        </div>
        
        <!-- Screen 2: Configuración -->
        <div class="screen" id="screen2">
            <div class="portal-info" id="portalDisplay" style="display: none;">
                <strong>Portal Bitrix24:</strong> <span id="portalDomainLabel"></span>
                <div class="status">Conexión establecida</div>
            </div>
            
            <form id="configForm">
                @csrf
                
                <!-- Configuración del Webhook -->
                <div class="form-group">
                    <label for="webhook_hash">Hash del Webhook de Salida</label>
                    <input 
                        type="password" 
                        id="webhook_hash" 
                        name="webhook_hash" 
                        placeholder="Ejemplo: eyJpdiI6IlU4WnU2Rkl..."
                        required
                    >
                    <div class="help-text">
                        El hash es único y será proporcionado por PREVI SRL.
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="auth_token">Token de Autorización</label>
                    <input 
                        type="password" 
                        id="auth_token" 
                        name="auth_token" 
                        placeholder="Ejemplo: n5ho5l2qzz9axqm..."
                        required
                    >
                    <div class="help-text">
                        El token de autenticación es el que Bitrix24 proporciona en el portal del cliente 
                        al configurar el webhook de salida.
                    </div>
                </div>
                
                <div class="section-divider"></div>
                
                <!-- Configuración del Bot -->
                <div class="form-group">
                    <label for="bot_name">Nombre del Bot</label>
                    <input 
                        type="text" 
                        id="bot_name" 
                        name="bot_name" 
                        placeholder="Ejemplo: Ánima Bot"
                        required
                    >
                    <div class="help-text">
                        Ingrese el nombre que desea que aparezca para el bot en las conversaciones.
                    </div>
                </div>
                
                <button type="submit" class="btn-primary" id="submitBtn">
                    Guardar Configuración
                </button>
            </form>
            
            <div class="navigation">
                <button type="button" class="btn-secondary" onclick="previousScreen()">
                    ← Anterior
                </button>
                <button type="button" class="btn-primary" onclick="nextScreen()" id="nextToChannels" style="display: none;">
                    Continuar →
                </button>
            </div>
        </div>
        
        <!-- Screen 3: Asignar Canales -->
        <div class="screen" id="screen3">
            <div class="instructions">
                <h3 data-lang="channel_instructions">Instrucciones para asignar el Bot a Canales</h3>
                <ol>
                    <li><span data-lang="step3_1">Ve a Centro de contacto → Canales</span></li>
                    <li><span data-lang="step3_2">Selecciona el canal deseado (Telegram, WhatsApp, etc.)</span></li>
                    <li><strong data-lang="queue_title">Cola:</strong>
                        <ul style="margin-top: 8px; margin-left: 20px;">
                            <li><span data-lang="queue_1">Cola del agente: Seleccionar los agentes deseados</span></li>
                            <li><span data-lang="queue_2">Distribuir comunicaciones entre las personas responsables: Elige el que más te convenga</span></li>
                            <li><span data-lang="queue_3">Información del agente: Utilizar el perfil de usuario del empleado</span></li>
                        </ul>
                    </li>
                    <li><strong data-lang="auto_actions">Acciones automáticas:</strong>
                        <ul style="margin-top: 8px; margin-left: 20px;">
                            <li><span data-lang="auto_1">Marcar la consulta como sin respuesta en: 1 Minuto (Recomendado)</span></li>
                            <li><span data-lang="auto_2">Si los empleados no responde a una comunicación: No hacer Nada (Recomendado)</span></li>
                            <li><span data-lang="auto_3">Si la consulta fue procesada y completada: No hacer Nada (Recomendado)</span></li>
                            <li><span data-lang="auto_4">Retrasar hasta que la consulta esté completamente cerrada: Cerrar Inmediatamente (Recomendado)</span></li>
                            <li><span data-lang="auto_5">Tiempo de espera de la conversación: Elige lo que desees</span></li>
                            <li><span data-lang="auto_6">Realizar una acción: No hacer Nada (Recomendado)</span></li>
                        </ul>
                    </li>
                    <li><strong data-lang="chatbots">Chatbots:</strong>
                        <ul style="margin-top: 8px; margin-left: 20px;">
                            <li><span data-lang="bot_1">✓ Asignar un bot de chat cuando se reciba la consulta de un cliente</span></li>
                            <li><span data-lang="bot_2">Seleccione un chat bot: Selecciona el bot instalado</span></li>
                            <li><span data-lang="bot_3">Activar chat bot: Cada vez que un cliente inicia una conversación</span></li>
                            <li><span data-lang="bot_4">Transferir después la conversación del bot a un agente en vivo: No transferir</span></li>
                            <li><span data-lang="bot_5">Desconectar el chat bot: Después de transferir a un agente</span></li>
                        </ul>
                    </li>
                </ol>
            </div>
            
            <!-- Image Carousel -->
            <div class="carousel-container">
                <div class="carousel-wrapper" id="carouselWrapper">
                    <div class="carousel-slide">
                        <img src="/img/Bot1.png" alt="Configuración Bot - Paso 1">
                    </div>
                    <div class="carousel-slide">
                        <img src="/img/Bot2.png" alt="Configuración Bot - Paso 2">
                    </div>
                    <div class="carousel-slide">
                        <img src="/img/Bot3.png" alt="Configuración Bot - Paso 3">
                    </div>
                    <div class="carousel-slide">
                        <img src="/img/Bot4.png" alt="Configuración Bot - Paso 4">
                    </div>
                    <div class="carousel-slide">
                        <img src="/img/Bot5.png" alt="Configuración Bot - Paso 5">
                    </div>
                    <div class="carousel-slide">
                        <img src="/img/Bot6.png" alt="Configuración Bot - Paso 6">
                    </div>
                    <div class="carousel-slide">
                        <img src="/img/Bot7.png" alt="Configuración Bot - Paso 7">
                    </div>
                    <div class="carousel-slide">
                        <img src="/img/Bot8.png" alt="Configuración Bot - Paso 8">
                    </div>
                    <div class="carousel-slide">
                        <img src="/img/Bot9.png" alt="Configuración Bot - Paso 9">
                    </div>
                    <div class="carousel-slide">
                        <img src="/img/Bot10.png" alt="Configuración Bot - Paso 10">
                    </div>
                    <div class="carousel-slide">
                        <img src="/img/Bot11.png" alt="Configuración Bot - Paso 11">
                    </div>
                </div>
                
                <div class="carousel-controls">
                    <button class="carousel-btn" onclick="previousSlide()">‹</button>
                    <button class="carousel-btn" onclick="nextSlide()">›</button>
                </div>
                
                <div class="carousel-indicators" id="carouselIndicators"></div>
            </div>
            
            <div class="info-box">
                <strong>✅ <span data-lang="channels_compatible">Canales compatibles:</span></strong> Telegram, WhatsApp Business, Facebook Messenger, Instagram, etc...
            </div>

            <div class="warning-box">
                <strong>💡 <span data-lang="tip">Consejo:</span></strong> <span data-lang="tip_message">Puedes asignar el bot a múltiples canales. 
                El bot responderá automáticamente en cada canal configurado.</span>
            </div>

            <div class="info-box" style="background: #d4f4dd; border-color: #a8e6b7; margin-top: 20px;">
                <strong>✅ <span data-lang="config_complete">Configuración Completada</span></strong><br>
                <span data-lang="can_close">Ya puedes cerrar esta ventana. El bot está listo para funcionar.</span>
            </div>
            
            <div class="navigation">
                <button type="button" class="btn-secondary" onclick="previousScreen()">
                    ← <span data-lang="previous">Anterior</span>
                </button>
            </div>
        </div>
        
        <div class="loading" id="loadingSpinner">
            <div class="spinner"></div>
            <p style="margin-top: 10px; color: #718096;">Guardando configuración...</p>
        </div>
        
        <div class="footer">
            <p>© 2025 Ánima Bot - Versión 1.0</p>
            <p style="margin-top: 5px;" data-lang="developed_by">
                Desarrollado por PREVI SRL
            </p>
        </div>
    </div>
    
    <script>
        let currentScreen = 1;

        let portalDomain = null;
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.has('DOMAIN')) {
            portalDomain = urlParams.get('DOMAIN');
        } else {
            // Fallback: intentar extraer del hostname
            const host = window.location.hostname;
            if (host.includes('bitrix24.')) {
                portalDomain = host;
            }
        }

        if (portalDomain) {
            const label = document.getElementById('portalDomainLabel');
            const container = document.getElementById('portalDisplay');
            if (label && container) {
                label.textContent = portalDomain;
                container.style.display = 'block';
            }
        }

        let configurationSaved = false;
        let currentLanguage = 'es';
        
        // Translation dictionary
        const translations = {
            es: {
                title: "Configuración Ánima Bot",
                step1: "Crear Webhook",
                step2: "Configurar Datos", 
                step3: "Asignar Canales",
                previous: "Anterior",
                continue: "Continuar",
                save_config: "Guardar Configuración",
                config_saved: "✓ Configuración Guardada",
                
                // Screen 1
                // Screen 1 - Pasos de instrucciones
                step1_1: "Ingresa a tu portal de Bitrix24",
                step1_2: "Ve a Recursos para desarrollador",
                step1_3: "Selecciona Otro → Webhook de Salida",
                step1_4: "URL de su controlador:",
                step1_5: "Token de aplicación: se genera automáticamente y deberá copiarlo después de guardar el Webhook de salida, será necesario para el siguiente paso",
                step1_6: "Eventos:",
                step1_7: "Guardar",
                step1_8: "Copiar Token de la aplicación y cerrar",

                webhook_instructions: "📋 Instrucciones para crear el Webhook de Salida",
                important: "⚠️ Importante:",
                hash_message: "El hash único será proporcionado por PREVI SRL. Contacta a soporte para obtenerlo.",
                hash_support: "Puede utilizar el siguiente hash para solicitar soporte con PREVI SRL:",
                note: "ℹ️ Nota:",
                note_message: "Necesitarás el token de autorización y el hash para completar el siguiente paso.",
                
                // Screen 2  
                portal_label: "Portal Bitrix24:",
                connection_status: "Conexión establecida",
                hash_label: "Hash del Webhook de Salida",
                hash_help: "El hash es único y será proporcionado por PREVI SRL.",
                token_label: "Token de Autorización",
                token_help: "El token de autenticación es el que Bitrix24 proporciona en el portal del cliente al configurar el webhook de salida.",
                bot_name_label: "Nombre del Bot",
                bot_name_help: "Ingrese el nombre que desea que aparezca para el bot en las conversaciones.",
                
                // Screen 3
                channel_instructions: "Instrucciones para asignar el Bot a Canales",
                step3_1: "Ve a Centro de contacto → Canales",
                step3_2: "Selecciona el canal deseado (Telegram, WhatsApp, etc.)",
                queue_title: "Cola:",
                queue_1: "Cola del agente: Seleccionar los agentes deseados",
                queue_2: "Distribuir comunicaciones entre las personas responsables: Elige el que más te convenga",
                queue_3: "Información del agente: Utilizar el perfil de usuario del empleado",
                auto_actions: "Acciones automáticas:",
                auto_1: "Marcar la consulta como sin respuesta en: 1 Minuto (Recomendado)",
                auto_2: "Si los empleados no responde a una comunicación: No hacer Nada (Recomendado)",
                auto_3: "Si la consulta fue procesada y completada: No hacer Nada (Recomendado)",
                auto_4: "Retrasar hasta que la consulta esté completamente cerrada: Cerrar Inmediatamente (Recomendado)",
                auto_5: "Tiempo de espera de la conversación: Elige lo que desees",
                auto_6: "Realizar una acción: No hacer Nada (Recomendado)",
                chatbots: "Chatbots:",
                bot_1: "✓ Asignar un bot de chat cuando se reciba la consulta de un cliente",
                bot_2: "Seleccione un chat bot: Selecciona el bot instalado",
                bot_3: "Activar chat bot: Cada vez que un cliente inicia una conversación",
                bot_4: "Transferir después la conversación del bot a un agente en vivo: No transferir",
                bot_5: "Desconectar el chat bot: Después de transferir a un agente",
                channels_compatible: "Canales compatibles:",
                tip: "Consejo:",
                tip_message: "Puedes asignar el bot a múltiples canales. El bot responderá automáticamente en cada canal configurado.",
                config_complete: "Configuración Completada",
                can_close: "Ya puedes cerrar esta ventana. El bot está listo para funcionar.",
                
                // Alerts
                fill_fields: "Por favor, completa todos los campos",
                save_before_continue: "Por favor, guarda la configuración antes de continuar",
                config_success: "✓ Configuración guardada correctamente",
                config_exists: "ℹ Configuración existente detectada",

                developed_by: "Desarrollado por PREVI SRL"
            },
            en: {
                title: "Ánima Bot Setup",
                step1: "Create Webhook",
                step2: "Configure Data",
                step3: "Assign Channels", 
                previous: "Previous",
                continue: "Continue",
                save_config: "Save Configuration",
                config_saved: "✓ Configuration Saved",
                
                // Screen 1
                // Screen 1 - Instruction steps
                step1_1: "Log into your Bitrix24 portal",
                step1_2: "Go to Developer Resources",
                step1_3: "Select Other → Outbound Webhook",
                step1_4: "Your controller URL:",
                step1_5: "Application token: generated automatically and you must copy it after saving the Outbound Webhook, it will be needed for the next step",
                step1_6: "Events:",
                step1_7: "Save",
                step1_8: "Copy Application Token and close",

                webhook_instructions: "📋 Instructions to create Outbound Webhook",
                important: "⚠️ Important:",
                hash_message: "The unique hash will be provided by PREVI SRL. Contact support to get it.",
                hash_support: "You can use the following hash to request support from PREVI SRL:",
                note: "ℹ️ Note:",
                note_message: "You will need the authorization token and hash to complete the next step.",
                
                // Screen 2
                portal_label: "Bitrix24 Portal:",
                connection_status: "Connection established",
                hash_label: "Outbound Webhook Hash",
                hash_help: "The hash is unique and will be provided by PREVI SRL.",
                token_label: "Authorization Token",
                token_help: "The authentication token that Bitrix24 provides in the client portal when configuring the outbound webhook.",
                bot_name_label: "Bot Name",
                bot_name_help: "Enter the name you want to appear for the bot in conversations.",
                
                // Screen 3
                channel_instructions: "Instructions to assign Bot to Channels",
                step3_1: "Go to Contact Center → Channels",
                step3_2: "Select the desired channel (Telegram, WhatsApp, etc.)",
                queue_title: "Queue:",
                queue_1: "Agent queue: Select the desired agents",
                queue_2: "Distribute communications among responsible persons: Choose what suits you best",
                queue_3: "Agent info: Use employee’s user profile",
                auto_actions: "Automatic actions:",
                auto_1: "Mark inquiry as unanswered after: 1 Minute (Recommended)",
                auto_2: "If employees don’t respond: Do Nothing (Recommended)",
                auto_3: "If the inquiry is processed and completed: Do Nothing (Recommended)",
                auto_4: "Delay until fully closed: Close Immediately (Recommended)",
                auto_5: "Conversation timeout: Choose what you want",
                auto_6: "Perform an action: Do Nothing (Recommended)",
                chatbots: "Chatbots:",
                bot_1: "✓ Assign a chatbot when a customer inquiry is received",
                bot_2: "Select the installed bot",
                bot_3: "Activate chatbot: Each time a customer starts a conversation",
                bot_4: "Transfer after chatbot: Do not transfer",
                bot_5: "Disconnect chatbot: After transfer to agent",
                channels_compatible: "Compatible channels:",
                tip: "Tip:",
                tip_message: "You can assign the bot to multiple channels. The bot will respond automatically on each configured channel.",
                config_complete: "Configuration Complete",
                can_close: "You can now close this window. The bot is ready to work.",
                
                // Alerts
                fill_fields: "Please fill in all fields",
                save_before_continue: "Please save the configuration before continuing",
                config_success: "✓ Configuration saved successfully",
                config_exists: "ℹ Existing configuration detected",

                developed_by: "Developed by PREVI SRL"
            }
        };
        
        function changeLanguage(lang) {
            currentLanguage = lang;
            
            // Update language buttons
            document.querySelectorAll('.lang-btn').forEach(btn => {
                btn.classList.toggle('active', btn.textContent.toLowerCase().includes(lang === 'es' ? 'español' : 'english'));
            });
            
            // Update all translatable elements
            updateTranslations();
        }
        
        function updateTranslations() {
            const t = translations[currentLanguage];
            
            // Update main title
            const h1 = document.querySelector('h1');
            if (h1) h1.textContent = t.title;
            
            // Update step labels
            document.querySelector('#step1 .step-label').textContent = t.step1;
            document.querySelector('#step2 .step-label').textContent = t.step2;
            document.querySelector('#step3 .step-label').textContent = t.step3;
            
            // Update data-lang elements
            document.querySelectorAll('[data-lang]').forEach(el => {
                const key = el.getAttribute('data-lang');
                if (t[key]) el.textContent = t[key];
            });
            
            // Update buttons
            document.querySelectorAll('button').forEach(btn => {
                if (btn.textContent.includes('Continuar') || btn.textContent.includes('Continue')) {
                    btn.innerHTML = `${t.continue} →`;
                }
                if (btn.textContent.includes('Anterior') || btn.textContent.includes('Previous')) {
                    btn.innerHTML = `← ${t.previous}`;
                }
            });
            
            // Update form labels and helps
            const hashLabel = document.querySelector('label[for="webhook_hash"]');
            if (hashLabel) {
                hashLabel.textContent = t.hash_label;
                hashLabel.nextElementSibling.nextElementSibling.textContent = t.hash_help;
            }
            
            const tokenLabel = document.querySelector('label[for="auth_token"]');
            if (tokenLabel) {
                tokenLabel.textContent = t.token_label;
                tokenLabel.nextElementSibling.nextElementSibling.textContent = t.token_help;
            }
            
            const botNameLabel = document.querySelector('label[for="bot_name"]');
            if (botNameLabel) {
                botNameLabel.textContent = t.bot_name_label;
                botNameLabel.nextElementSibling.nextElementSibling.textContent = t.bot_name_help;
            }
        }
        
        function updateProgressBar() {
            // Update step indicators
            for (let i = 1; i <= 3; i++) {
                const step = document.getElementById(`step${i}`);
                step.classList.remove('active', 'completed');
                
                if (i < currentScreen) {
                    step.classList.add('completed');
                } else if (i === currentScreen) {
                    step.classList.add('active');
                }
            }
        }
        
        function showScreen(screenNumber) {
            // Hide all screens
            document.querySelectorAll('.screen').forEach(screen => {
                screen.classList.remove('active');
            });
            
            // Show selected screen
            document.getElementById(`screen${screenNumber}`).classList.add('active');
            
            currentScreen = screenNumber;
            updateProgressBar();
        }
        
        function nextScreen() {
            if (currentScreen === 2 && !configurationSaved) {
                showAlert('Por favor, guarda la configuración antes de continuar', 'error');
                return;
            }
            
            if (currentScreen < 3) {
                showScreen(currentScreen + 1);
            }
        }
        
        function previousScreen() {
            if (currentScreen > 1) {
                showScreen(currentScreen - 1);
            }
        }
        
        function finishSetup() {
            showAlert('✓ Configuración completada exitosamente', 'success');
            setTimeout(() => {
                window.location.reload();
            }, 2000);
        }
        
        // Form submission
        document.getElementById('configForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const alertDiv = document.getElementById('alertMessage');
            const submitBtn = document.getElementById('submitBtn');
            const loadingSpinner = document.getElementById('loadingSpinner');
            
            // Get form values
            const webhookHash = document.getElementById('webhook_hash').value.trim();
            const authToken = document.getElementById('auth_token').value.trim();
            const botName = document.getElementById('bot_name').value.trim();
            
            // Basic validation
            if (!webhookHash || !authToken || !botName) {
                showAlert('Por favor, completa todos los campos', 'error');
                return;
            }
            
            // Show loading state
            submitBtn.disabled = true;
            submitBtn.textContent = 'Guardando...';
            loadingSpinner.style.display = 'block';
            alertDiv.style.display = 'none';
            
            let configSuccess = false;
            let botNameSuccess = false;
            
            try {
                // Save configuration
                const configResponse = await fetch('/api/v1.0.0/webhook/config', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
                    },
                    body: JSON.stringify({
                        webhook_hash: webhookHash,
                        auth_token: authToken,
                        bot_name: botName,
                        portal: portalDomain
                    })
                });
                
                const configData = await configResponse.json();
                console.log('Respuesta configuración:', configData);
                
                if (configResponse.ok && configData.success) {
                    configSuccess = true;
                    
                    // Try to update bot name in Bitrix
                    if (configData.bot_name_saved && botName) {
                        try {
                            const botNameResponse = await fetch('/api/v1.0.0/webhook/update-bot-name', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
                                },
                                body: JSON.stringify({
                                    portal: portalDomain,
                                    bot_name: botName
                                })
                            });
                            
                            const botNameData = await botNameResponse.json();
                            console.log('Respuesta actualización nombre bot:', botNameData);
                            
                            if (botNameResponse.ok && botNameData.success) {
                                botNameSuccess = true;
                            }
                        } catch (botError) {
                            console.warn('Error al actualizar nombre del bot:', botError);
                        }
                    }
                    
                    // Show success message
                    if (configSuccess) {
                        configurationSaved = true;
                        showAlert('✓ Configuración guardada correctamente', 'success');
                        
                        // Disable form and show next button
                        document.getElementById('webhook_hash').disabled = true;
                        document.getElementById('auth_token').disabled = true;
                        document.getElementById('bot_name').disabled = true;
                        submitBtn.textContent = '✓ Configuración Guardada';
                        submitBtn.disabled = true;
                        
                        // Show continue button
                        document.getElementById('nextToChannels').style.display = 'block';
                    }
                } else {
                    throw new Error(configData.message || 'Error al guardar configuración');
                }
                
            } catch (error) {
                showAlert('Error: ' + error.message, 'error');
                console.error('Error durante la configuración:', error);
            } finally {
                if (!configurationSaved) {
                    submitBtn.disabled = false;
                    submitBtn.textContent = 'Guardar Configuración';
                }
                loadingSpinner.style.display = 'none';
            }
        });
        
        function showAlert(message, type) {
            const alertDiv = document.getElementById('alertMessage');
            alertDiv.textContent = message;
            alertDiv.className = `alert alert-${type}`;
            alertDiv.style.display = 'block';
            
            setTimeout(() => {
                alertDiv.style.display = 'none';
            }, 7000);
        }
        
        // Check existing configuration on load
        window.addEventListener('load', async () => {
            try {
                const response = await fetch(`/api/v1.0.0/webhook/config?portal=${portalDomain}`);
                const data = await response.json();
                
                if (data.success && data.has_config) {
                    console.log('Configuración existente detectada:', data);
                    configurationSaved = true;
                    
                    // Jump to screen 2 and show data
                    showScreen(2);
                    
                    const hashInput = document.getElementById('webhook_hash');
                    const tokenInput = document.getElementById('auth_token');
                    const botNameInput = document.getElementById('bot_name');
                    const submitBtn = document.getElementById('submitBtn');
                    
                    // Show masked values
                    if (data.hash_length > 0) {
                        hashInput.value = '••••••••••••••••••••';
                        hashInput.disabled = true;
                    }
                    
                    if (data.token_length > 0) {
                        tokenInput.value = '••••••••••••••••••••';
                        tokenInput.disabled = true;
                    }
                    
                    if (data.bot_name) {
                        botNameInput.value = data.bot_name;
                        botNameInput.disabled = true;
                    }
                    
                    // Update button state
                    submitBtn.textContent = '✓ Configuración Guardada';
                    submitBtn.disabled = true;
                    
                    // Show continue button
                    document.getElementById('nextToChannels').style.display = 'block';
                    
                    showAlert('ℹ Configuración existente detectada', 'info');
                }
            } catch (error) {
                console.error('Error al verificar configuración:', error);
            }
        });
        
        // Real-time validation
        document.getElementById('webhook_hash').addEventListener('input', function(e) {
            const value = e.target.value;
            if (value.length > 0 && value.length < 10) {
                e.target.style.borderColor = '#fc8181';
            } else if (value.length >= 10) {
                e.target.style.borderColor = '#48bb78';
            } else {
                e.target.style.borderColor = '#e1e8ed';
            }
        });
        
        document.getElementById('auth_token').addEventListener('input', function(e) {
            const value = e.target.value;
            if (value.length > 0 && value.length < 10) {
                e.target.style.borderColor = '#fc8181';
            } else if (value.length >= 10) {
                e.target.style.borderColor = '#48bb78';
            } else {
                e.target.style.borderColor = '#e1e8ed';
            }
        });
        
        document.getElementById('bot_name').addEventListener('input', function(e) {
            const value = e.target.value;
            if (value.length > 0 && value.length < 3) {
                e.target.style.borderColor = '#fc8181';
            } else if (value.length >= 3) {
                e.target.style.borderColor = '#48bb78';
            } else {
                e.target.style.borderColor = '#e1e8ed';
            }
        });
    </script>

    <script>
        let currentSlide = 0;
        const slides = document.querySelectorAll('.carousel-slide');
        const carouselWrapper = document.getElementById('carouselWrapper');
        const indicatorsContainer = document.getElementById('carouselIndicators');

        function updateCarousel() {
            const offset = -currentSlide * 100;
            carouselWrapper.style.transform = `translateX(${offset}%)`;

            // Update indicators
            document.querySelectorAll('.carousel-dot').forEach((dot, index) => {
                dot.classList.toggle('active', index === currentSlide);
            });
        }

        function previousSlide() {
            currentSlide = (currentSlide === 0) ? slides.length - 1 : currentSlide - 1;
            updateCarousel();
        }

        function nextSlide() {
            currentSlide = (currentSlide + 1) % slides.length;
            updateCarousel();
        }

        function createIndicators() {
            slides.forEach((_, index) => {
                const dot = document.createElement('div');
                dot.classList.add('carousel-dot');
                if (index === 0) dot.classList.add('active');
                dot.addEventListener('click', () => {
                    currentSlide = index;
                    updateCarousel();
                });
                indicatorsContainer.appendChild(dot);
            });
        }

        window.addEventListener('load', () => {
            createIndicators();
            updateCarousel();
        });
    </script>
</body>
</html>
