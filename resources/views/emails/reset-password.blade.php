<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Restablecer Contraseña - Consultor UNESYS</title>
    <style>
        body { 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
            background-color: #f3f4f6; /* --color-base-200 */
            color: #1f2937; /* --color-base-content */
            margin: 0; 
            padding: 0; 
            -webkit-font-smoothing: antialiased;
        }
        .wrapper {
            width: 100%;
            background-color: #f3f4f6;
            padding: 40px 0;
        }
        .container { 
            max-width: 600px; 
            margin: 0 auto; 
            background-color: #ffffff; /* --color-base-100 */
            padding: 40px; 
            border-radius: 1.5rem; /* --rounded-box */
            border: 1px solid #e5e7eb; /* --color-base-300 */
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
        }
        .header { 
            border-bottom: 3px solid #6A9149; /* --color-primary */
            padding-bottom: 20px; 
            margin-bottom: 25px; 
        }
        .header h1 { 
            font-size: 22px; 
            color: #6A9149; /* --color-primary */
            margin: 0; 
            font-weight: 700;
        }
        .saludo {
            font-size: 16px;
            margin-bottom: 15px;
        }
        .texto {
            font-size: 15px;
            line-height: 1.6;
            color: #1f2937;
        }
        .btn-container {
            text-align: center;
            margin: 30px 0;
        }
        .btn { 
            display: inline-block; 
            padding: 12px 30px; 
            background-color: #6A9149; /* --color-primary */
            color: #ffffff !important; /* --color-primary-content */
            text-decoration: none; 
            border-radius: 0.75rem; /* --rounded-btn */
            font-weight: 600; 
            font-size: 15px;
            box-shadow: 0 2px 4px rgba(106, 145, 73, 0.2);
            transition: background-color 0.2s;
        }
        .alerta {
            background-color: #ffffff;
            border-left: 4px solid #CC9933; /* --color-secondary / warning */
            padding: 15px;
            margin: 25px 0;
            font-size: 14px;
            color: #1f2937;
            border-radius: 0.5rem; /* --rounded-selector */
            background-color: rgba(204, 153, 51, 0.05); /* Toque sutil de fondo secondary */
        }
        .footer { 
            font-size: 12px; 
            color: #6b7280; 
            margin-top: 35px; 
            border-top: 1px solid #e5e7eb; /* --color-base-300 */
            padding-top: 20px; 
            text-align: center;
            line-height: 1.5;
        }
    </style>
</head>
<body>

    <div class="wrapper">
        <div class="container">
            <div class="header">
                <h1>Consultor UNESYS</h1>
            </div>

            <p class="saludo">Estimado/a <strong>{{ $nombre }}</strong>,</p>
            
            <p class="texto">
                Recibimos una solicitud para restablecer la contraseña de acceso a su cuenta en el consultor UNESYS.
            </p>
            
            <p class="texto" style="margin-bottom: 10px;">
                Para continuar con el proceso y asignar una nueva clave, haga clic en el siguiente enlace:
            </p>

            <div class="btn-container">
                <a href="{{ $url }}" class="btn">Restablecer Contraseña</a>
            </div>

            <p class="texto" style="font-size: 13px; color: #6b7280;">
                Este enlace de recuperación es de un solo uso y expirará automáticamente en 60 minutos.
            </p>
            
            <div class="alerta">
                <strong>Atención:</strong> Si usted no inició esta solicitud, ignore este mensaje. Su contraseña actual permanecerá segura y sin modificaciones.
            </div>

            <div class="footer">
                <p style="margin: 0 0 5px 0;">Este es un correo automático generado por el sistema, por favor no lo responda.</p>
                <p style="margin: 0; font-weight: bold;">&copy; {{ date('Y') }} - Dirección General de Tecnología e Innovación</p>
            </div>
        </div>
    </div>

</body>
</html>