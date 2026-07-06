import userEvent from '@testing-library/user-event';
import { useEffect } from 'react';
import { render, screen, waitFor } from '../test/test-utils';
import { ToastProvider, showGlobalToast, useToast } from './useToast';

function ShowToastButton({ message = 'Hello world', severity }: { message?: string; severity?: 'success' | 'error' }) {
  const { showToast } = useToast();
  return <button onClick={() => showToast(message, severity)}>Trigger</button>;
}

describe('ToastProvider / useToast', () => {
  it('renders children without showing a toast initially', () => {
    render(
      <ToastProvider>
        <div>content</div>
      </ToastProvider>,
    );

    expect(screen.getByText('content')).toBeInTheDocument();
    expect(screen.queryByRole('alert')).not.toBeInTheDocument();
  });

  it('shows a toast with the message when showToast is called', async () => {
    const user = userEvent.setup();
    render(
      <ToastProvider>
        <ShowToastButton message="Quotation created." />
      </ToastProvider>,
    );

    await user.click(screen.getByText('Trigger'));

    expect(await screen.findByText('Quotation created.')).toBeInTheDocument();
  });

  it('defaults to success severity and respects an explicit severity override', async () => {
    const user = userEvent.setup();
    render(
      <ToastProvider>
        <ShowToastButton message="Something failed." severity="error" />
      </ToastProvider>,
    );

    await user.click(screen.getByText('Trigger'));

    const alert = await screen.findByRole('alert');
    expect(alert.className).toMatch(/colorError|MuiAlert-filledError/);
  });

  it('showGlobalToast surfaces a toast from outside any component tree', async () => {
    function Mounter() {
      useEffect(() => {
        showGlobalToast('From outside React', 'error');
      }, []);
      return null;
    }

    render(
      <ToastProvider>
        <Mounter />
      </ToastProvider>,
    );

    await waitFor(() => {
      expect(screen.getByText('From outside React')).toBeInTheDocument();
    });
  });
});
