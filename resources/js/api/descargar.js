import client from './client';

function nombreArchivo(response, porDefecto) {
    const disposition = response.headers?.['content-disposition'] || '';
    const coincide = disposition.match(/filename="?([^";]+)"?/);
    return coincide ? coincide[1] : porDefecto;
}

/**
 * Descarga un recurso protegido con Bearer (los <a href> no envían el token).
 */
export async function descargar(url, porDefecto = 'descarga') {
    const response = await client.get(url, { responseType: 'blob' });
    const objectUrl = URL.createObjectURL(new Blob([response.data]));
    const link = document.createElement('a');
    link.href = objectUrl;
    link.download = nombreArchivo(response, porDefecto);
    document.body.appendChild(link);
    link.click();
    link.remove();
    URL.revokeObjectURL(objectUrl);
}

/**
 * Abre un recurso protegido (p. ej. el PDF del volante) en una pestaña nueva.
 */
export async function abrirEnNuevaVentana(url, tipo = 'application/pdf') {
    const response = await client.get(url, { responseType: 'blob' });
    const objectUrl = URL.createObjectURL(new Blob([response.data], { type: tipo }));
    window.open(objectUrl, '_blank');
    setTimeout(() => URL.revokeObjectURL(objectUrl), 60_000);
}
