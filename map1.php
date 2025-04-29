<?php
// Função para obter o IP real do usuário
function getRealIpAddr() {
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        $ip = $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
    } else {
        $ip = $_SERVER['REMOTE_ADDR'];
    }
    return $ip;
}

// Obter IP do usuário
$userIp = getRealIpAddr();

// Obter a localização do usuário usando a API GeoIP
function getUserLocation($ip) {
    try {
        $response = file_get_contents("http://ip-api.com/json/{$ip}");
        $geoData = json_decode($response, true);
        
        if ($geoData && $geoData['status'] === 'success') {
            return [
                'city' => $geoData['city'],
                'country' => $geoData['country'],
                'lat' => $geoData['lat'],
                'lon' => $geoData['lon']
            ];
        } else {
            // Valores padrão caso a API falhe
            return [
                'city' => 'sua cidade',
                'country' => 'Brasil',
                'lat' => -23.5505,  // São Paulo como padrão
                'lon' => -46.6333
            ];
        }
    } catch (Exception $e) {
        // Valores padrão em caso de erro
        return [
            'city' => 'sua cidade',
            'country' => 'Brasil',
            'lat' => -23.5505,  // São Paulo como padrão
            'lon' => -46.6333
        ];
    }
}

// Obter localização do usuário
$userLocation = getUserLocation($userIp);
$city = $userLocation['city'];
$country = $userLocation['country'];
$userLat = $userLocation['lat'];
$userLon = $userLocation['lon'];

// Perfis próximos - em uma aplicação real, estes seriam obtidos do banco de dados
// com base na localização do usuário
$profiles = [
    ['id' => 1, 'lat' => $userLat + 0.003, 'lon' => $userLon - 0.005, 'status' => 'live'],
    ['id' => 2, 'lat' => $userLat - 0.002, 'lon' => $userLon + 0.004, 'status' => 'live'],
    ['id' => 3, 'lat' => $userLat + 0.001, 'lon' => $userLon - 0.002, 'status' => 'live'],
    ['id' => 4, 'lat' => $userLat - 0.004, 'lon' => $userLon - 0.003, 'status' => 'live'],
    ['id' => 5, 'lat' => $userLat + 0.006, 'lon' => $userLon + 0.001, 'status' => 'recent_30s'],
    ['id' => 6, 'lat' => $userLat - 0.005, 'lon' => $userLon + 0.006, 'status' => 'recent_2m'],
    ['id' => 7, 'lat' => $userLat + 0.008, 'lon' => $userLon - 0.008, 'status' => ''],
    ['id' => 8, 'lat' => $userLat - 0.009, 'lon' => $userLon - 0.005, 'status' => '']
];

// Converter os dados para formato JSON para uso no JavaScript
$locationJson = json_encode($userLocation);
$profilesJson = json_encode($profiles);
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dating App</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.css" />
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            width: 100%;
            height: 100vh;
            overflow: hidden;
            position: relative;
            background-color: #121212;
            color: #f8f8f8;
        }

        .map-container {
            width: 100%;
            height: 100%;
            position: relative;
            overflow: hidden;
        }

        #map {
            width: 100%;
            height: 100%;
            position: absolute;
            top: 0;
            left: 0;
            z-index: 1;
        }

        .map-overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
            z-index: 2;
            box-shadow: inset 0 0 200px rgba(0, 0, 0, 0.8);
        }

        /* Profile markers */
        .profile-marker {
            position: relative;
        }

        .profile-marker-inner {
            width: 56px;
            height: 56px;
            border-radius: 50%;
            background-color: #1e1e1e;
            border: 2px solid rgba(255, 255, 255, 0.2);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.5), 0 0 20px rgba(255, 120, 180, 0.2);
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
        }

        .profile-marker-inner:hover {
            transform: scale(1.1);
            border-color: rgba(255, 120, 180, 0.8);
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.5), 0 0 30px rgba(255, 120, 180, 0.4);
        }

        .profile-marker-inner img {
            width: 100%;
            height: 100%;
            border-radius: 50%;
            object-fit: cover;
            filter: brightness(1.1) contrast(1.1);
        }

        /* Profile status labels */
        .profile-status {
            position: absolute;
            top: -22px;
            left: 50%;
            transform: translateX(-50%);
            background: linear-gradient(135deg, #ff2e63, #ff0844);
            padding: 3px 8px;
            border-radius: 10px;
            font-size: 10px;
            font-weight: bold;
            color: white;
            white-space: nowrap;
            box-shadow: 0 2px 8px rgba(255, 40, 90, 0.6);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            z-index: 1000;
        }

        .profile-status.live {
            background: linear-gradient(135deg, #ff2e63, #ff0844);
            box-shadow: 0 2px 8px rgba(255, 40, 90, 0.6), 0 0 15px rgba(255, 40, 90, 0.4);
            animation: pulseLive 1.5s infinite;
        }

        .profile-status.recent {
            background: linear-gradient(135deg, #3d72b4, #1e3c72);
            font-size: 9px;
            box-shadow: 0 2px 8px rgba(30, 60, 114, 0.5);
        }

        @keyframes pulseLive {
            0% {
                box-shadow: 0 2px 8px rgba(255, 40, 90, 0.6), 0 0 5px rgba(255, 40, 90, 0.4);
            }
            50% {
                box-shadow: 0 2px 8px rgba(255, 40, 90, 0.6), 0 0 20px rgba(255, 40, 90, 0.7);
            }
            100% {
                box-shadow: 0 2px 8px rgba(255, 40, 90, 0.6), 0 0 5px rgba(255, 40, 90, 0.4);
            }
        }

        /* Bottom card */
        .bottom-card {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            background: linear-gradient(to bottom, rgba(30, 30, 30, 0.85), rgba(20, 20, 20, 0.95));
            border-top-left-radius: 24px;
            border-top-right-radius: 24px;
            padding: 28px 24px;
            z-index: 20;
            box-shadow: 0 -10px 25px rgba(0, 0, 0, 0.5);
            pointer-events: auto;
            backdrop-filter: blur(10px);
            border-top: 1px solid rgba(255, 255, 255, 0.1);
        }

        .location-indicator {
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 18px;
        }

        .location-dot {
            width: 28px;
            height: 28px;
            background: linear-gradient(135deg, #36d1dc, #5b86e5);
            border-radius: 50%;
            margin-right: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 2px 12px rgba(91, 134, 229, 0.4), 0 0 15px rgba(54, 209, 220, 0.3);
            position: relative;
        }

        .location-dot::after {
            content: "";
            width: 14px;
            height: 14px;
            background-color: white;
            border-radius: 50%;
            box-shadow: inset 0 0 5px rgba(0, 0, 0, 0.2);
        }

        .location-dot::before {
            content: "";
            position: absolute;
            width: 40px;
            height: 40px;
            background: transparent;
            border-radius: 50%;
            border: 2px solid rgba(91, 134, 229, 0.3);
            animation: ripple 1.5s infinite ease-out;
        }

        @keyframes ripple {
            0% {
                transform: scale(0.8);
                opacity: 1;
            }
            100% {
                transform: scale(1.5);
                opacity: 0;
            }
        }

        .location-text {
            font-size: 18px;
            font-weight: 500;
            color: #f8f8f8;
            text-shadow: 0 2px 5px rgba(0, 0, 0, 0.3);
        }

        .main-headline {
            font-size: 28px;
            font-weight: bold;
            text-align: center;
            margin-bottom: 28px;
            color: #f8f8f8;
            line-height: 1.3;
            text-shadow: 0 2px 5px rgba(0, 0, 0, 0.3);
            background: linear-gradient(to right, #ff8a00, #ff2e63);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            padding: 5px 0;
        }

        .button-container {
            display: flex;
            justify-content: center;
            padding: 10px;
        }

        .man-button {
            background: linear-gradient(135deg, #2c3e50, #4a4a4a);
            border: none;
            border-radius: 16px;
            padding: 20px;
            width: 100%;
            max-width: 300px;
            display: flex;
            flex-direction: column;
            align-items: center;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.3), 0 0 15px rgba(255, 255, 255, 0.05);
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .man-button::after {
            content: "";
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.1), transparent);
            transition: 0.5s;
        }

        .man-button:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 28px rgba(0, 0, 0, 0.4), 0 0 30px rgba(255, 255, 255, 0.1);
            background: linear-gradient(135deg, #3a4b5f, #5a5a5a);
        }

        .man-button:hover::after {
            left: 100%;
        }

        .button-emoji {
            font-size: 32px;
            margin-bottom: 10px;
            text-shadow: 0 2px 5px rgba(0, 0, 0, 0.3);
        }

        .button-text {
            font-size: 20px;
            font-weight: 500;
            color: #f8f8f8;
            text-shadow: 0 2px 5px rgba(0, 0, 0, 0.3);
        }

        /* Leaflet customizations */
        .leaflet-control-zoom {
            background-color: rgba(35, 35, 35, 0.85) !important;
            border-radius: 8px !important;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.4), 0 0 15px rgba(255, 255, 255, 0.05) !important;
            border: 1px solid rgba(255, 255, 255, 0.1) !important;
            backdrop-filter: blur(5px);
        }

        .leaflet-control-zoom a {
            background-color: transparent !important;
            color: #f8f8f8 !important;
            border-color: rgba(255, 255, 255, 0.1) !important;
            width: 40px !important;
            height: 40px !important;
            line-height: 40px !important;
            font-size: 24px !important;
            font-weight: bold !important;
            transition: background-color 0.2s ease !important;
        }

        .leaflet-control-zoom a:hover {
            background-color: rgba(255, 255, 255, 0.1) !important;
        }
    </style>
</head>
<body>
    <div class="map-container">
        <div id="map"></div>
        <div class="map-overlay"></div>
    </div>

    <!-- Bottom card -->
    <div class="bottom-card">
        <div class="location-indicator">
            <div class="location-dot"></div>
            <span class="location-text" id="location-text"><?php echo htmlspecialchars($city); ?>, <?php echo htmlspecialchars($country); ?></span>
        </div>
        <h1 class="main-headline" id="main-headline">É hora de dar o "match" na sua história de amor em <?php echo htmlspecialchars($city); ?>!</h1>
        <div class="button-container">
            <button class="man-button" id="man-button">
                <span class="button-emoji">👨</span>
                <span class="button-text">Eu sou um homem</span>
            </button>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.js"></script>
    <script>
        // Dados do PHP convertidos para variáveis JavaScript
        const userLocation = <?php echo $locationJson; ?>;
        const profiles = <?php echo $profilesJson; ?>;
        
        // Inicializar o mapa com a localização do usuário
        const map = L.map('map', {
            center: [userLocation.lat, userLocation.lon],
            zoom: 15,
            zoomControl: true,
            attributionControl: false, // Remover atribuição para estética limpa
            scrollWheelZoom: true
        });
        
        // Adicionar tiles do OpenStreetMap com estilo mais escuro
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            minZoom: 3,
            tileSize: 256,
            zoomOffset: 0
        }).addTo(map);
        
        // Função para criar um ícone de marcador personalizado
        function createMarkerIcon(profileId) {
            return L.divIcon({
                className: 'profile-marker',
                html: `
                    <div class="profile-marker-inner">
                        <img src="/api/placeholder/56/56?text=${profileId}" alt="Profile ${profileId}">
                    </div>
                `,
                iconSize: [56, 56],
                iconAnchor: [28, 56]
            });
        }
        
        // Função para adicionar status ao marcador
        function addStatusToMarker(marker, status) {
            const element = marker.getElement();
            if (!element) return;
            
            const statusDiv = document.createElement('div');
            
            if (status === 'live') {
                statusDiv.className = 'profile-status live';
                statusDiv.textContent = 'AO VIVO';
            } else if (status === 'recent_30s') {
                statusDiv.className = 'profile-status recent';
                statusDiv.textContent = '30 SEGUNDOS ATRÁS';
            } else if (status === 'recent_2m') {
                statusDiv.className = 'profile-status recent';
                statusDiv.textContent = '2 MINUTOS ATRÁS';
            }
            
            if (status) {
                element.prepend(statusDiv);
            }
        }
        
        // Adicionar perfis ao mapa
        profiles.forEach(profile => {
            const marker = L.marker([profile.lat, profile.lon], {
                icon: createMarkerIcon(profile.id)
            }).addTo(map);
            
            // Adicionar status após um curto delay para garantir que o elemento DOM esteja pronto
            setTimeout(() => {
                addStatusToMarker(marker, profile.status);
            }, 100);
        });
        
        // Lidar com o clique no botão "Eu sou um homem"
        document.getElementById('man-button').addEventListener('click', function() {
            window.open('https://www.google.com', '_blank');
        });
    </script>
</body>
</html>
