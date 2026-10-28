<?php
// src/functions/imagem_functions.php
// Guarda as imagens enviadas já reduzidas, para o disco do servidor não encher:
// foto de celular (3–5 MB) vira 150–400 KB sem diferença visível na tela.
//   - lado maior de no máximo IMAGEM_LADO_MAXIMO px (proporção mantida)
//   - gira conforme a orientação gravada pela câmera (EXIF), senão fica deitada
//   - tira os metadados (GPS, modelo do celular...) — ninguém vê de onde a foto saiu
//   - WebP por padrão; JPEG quando a imagem pode ser compartilhada em redes
//     sociais (foto de anúncio vira a prévia do link no WhatsApp/Facebook)
// Sem a extensão GD no servidor (ou com imagem que o GD não abre), guarda o
// arquivo original como chegou: o upload nunca falha por causa da redução.

const IMAGEM_LADO_MAXIMO = 1600;
const IMAGEM_QUALIDADE = 80;

/**
 * Salva a imagem enviada ($tmp, de $_FILES) em $destinoSemExtensao + extensão final.
 * $formato: 'webp' ou 'jpg'. Retorna o caminho completo salvo, ou null se falhou.
 * A validação do tipo do arquivo continua sendo de quem chama (antes desta função).
 */
function salvarImagemOtimizada(string $tmp, string $destinoSemExtensao, string $formato = 'webp'): ?string
{
    $info = @getimagesize($tmp);
    // GIF fica de fora: o GD perderia a animação (é guardado como veio)
    $tipos = [IMAGETYPE_JPEG => 'imagecreatefromjpeg', IMAGETYPE_PNG => 'imagecreatefrompng',
              IMAGETYPE_WEBP => 'imagecreatefromwebp'];
    $abrir = $info ? ($tipos[$info[2]] ?? null) : null;
    if ($formato === 'webp' && !function_exists('imagewebp')) {
        $formato = 'jpg';
    }

    $imagem = ($abrir && function_exists($abrir) && function_exists('imagecreatetruecolor')) ? @$abrir($tmp) : false;
    if (!$imagem) {
        // Sem GD (ou formato que ele não abre): guarda como veio, com a extensão original
        $extensao = $info ? (image_type_to_extension($info[2], false) ?: 'jpg') : 'jpg';
        $destino = $destinoSemExtensao . '.' . ($extensao === 'jpeg' ? 'jpg' : $extensao);
        return (is_uploaded_file($tmp) ? move_uploaded_file($tmp, $destino) : rename($tmp, $destino)) ? $destino : null;
    }

    // Orientação da câmera (só JPEG tem EXIF)
    if ($info[2] === IMAGETYPE_JPEG && function_exists('exif_read_data')) {
        $exif = @exif_read_data($tmp);
        $graus = [3 => 180, 6 => -90, 8 => 90][$exif['Orientation'] ?? 1] ?? 0;
        if ($graus) {
            $girada = imagerotate($imagem, $graus, 0);
            if ($girada) {
                unset($imagem);
                $imagem = $girada;
            }
        }
    }

    $largura = imagesx($imagem);
    $altura = imagesy($imagem);
    $escala = min(1, IMAGEM_LADO_MAXIMO / max($largura, $altura));
    $novaLargura = max(1, (int) round($largura * $escala));
    $novaAltura = max(1, (int) round($altura * $escala));

    $nova = imagecreatetruecolor($novaLargura, $novaAltura);
    if ($formato === 'webp') {
        // mantém a transparência de PNG/GIF
        imagealphablending($nova, false);
        imagesavealpha($nova, true);
        imagefill($nova, 0, 0, imagecolorallocatealpha($nova, 0, 0, 0, 127));
    } else {
        // JPEG não tem transparência: fundo branco
        imagefill($nova, 0, 0, imagecolorallocate($nova, 255, 255, 255));
    }
    imagecopyresampled($nova, $imagem, 0, 0, 0, 0, $novaLargura, $novaAltura, $largura, $altura);
    unset($imagem);

    $destino = $destinoSemExtensao . '.' . $formato;
    $ok = $formato === 'webp' ? imagewebp($nova, $destino, IMAGEM_QUALIDADE) : imagejpeg($nova, $destino, IMAGEM_QUALIDADE + 5);
    unset($nova);
    if (!$ok) {
        @unlink($destino);
        return null;
    }
    @unlink($tmp);
    return $destino;
}
