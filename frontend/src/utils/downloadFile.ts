import i18n from '../i18n';
import { showGlobalToast } from '../hooks/useToast';

/**
 * Fetches a blob-returning endpoint and saves it as a file, showing an
 * immediate "Downloading…" toast so the user isn't left wondering whether
 * anything happened during the network round trip, followed by a success
 * toast once the browser download is triggered. Failures surface via the
 * global axios error-toast interceptor, same as any other request.
 */
export async function downloadBlobAsFile(fetchBlob: () => Promise<Blob>, filename: string): Promise<void> {
  showGlobalToast(i18n.t('common:toast.downloading'), 'info');

  const blob = await fetchBlob();

  const url = window.URL.createObjectURL(blob);
  const link = document.createElement('a');
  link.href = url;
  link.download = filename;
  link.click();
  window.URL.revokeObjectURL(url);

  showGlobalToast(i18n.t('common:toast.downloaded'), 'success');
}
