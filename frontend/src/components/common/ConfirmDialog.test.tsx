import userEvent from '@testing-library/user-event';
import { vi } from 'vitest';
import { render, screen } from '../../test/test-utils';
import { ConfirmDialog } from './ConfirmDialog';

describe('ConfirmDialog', () => {
  it('does not render its content when closed', () => {
    render(
      <ConfirmDialog
        open={false}
        title="Delete quotation"
        message="Are you sure?"
        onConfirm={vi.fn()}
        onCancel={vi.fn()}
      />,
    );

    expect(screen.queryByText('Delete quotation')).not.toBeInTheDocument();
  });

  it('renders title, message, and default labels when open', () => {
    render(
      <ConfirmDialog
        open
        title="Delete quotation"
        message="Are you sure you want to delete quotation QT-2026-00001?"
        onConfirm={vi.fn()}
        onCancel={vi.fn()}
      />,
    );

    expect(screen.getByText('Delete quotation')).toBeInTheDocument();
    expect(screen.getByText('Are you sure you want to delete quotation QT-2026-00001?')).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Confirm' })).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Cancel' })).toBeInTheDocument();
  });

  it('calls onConfirm when the confirm button is clicked', async () => {
    const user = userEvent.setup();
    const onConfirm = vi.fn();

    render(
      <ConfirmDialog open title="Delete quotation" message="Are you sure?" onConfirm={onConfirm} onCancel={vi.fn()} />,
    );

    await user.click(screen.getByRole('button', { name: 'Confirm' }));

    expect(onConfirm).toHaveBeenCalledTimes(1);
  });

  it('calls onCancel when the cancel button is clicked', async () => {
    const user = userEvent.setup();
    const onCancel = vi.fn();

    render(
      <ConfirmDialog open title="Delete quotation" message="Are you sure?" onConfirm={vi.fn()} onCancel={onCancel} />,
    );

    await user.click(screen.getByRole('button', { name: 'Cancel' }));

    expect(onCancel).toHaveBeenCalledTimes(1);
  });

  it('respects custom confirmLabel/cancelLabel', () => {
    render(
      <ConfirmDialog
        open
        title="Change role"
        message="Are you sure?"
        confirmLabel="Change role"
        cancelLabel="Never mind"
        danger={false}
        onConfirm={vi.fn()}
        onCancel={vi.fn()}
      />,
    );

    expect(screen.getByRole('button', { name: 'Change role' })).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Never mind' })).toBeInTheDocument();
  });

  it('disables both buttons while loading', () => {
    render(
      <ConfirmDialog open title="Delete quotation" message="Are you sure?" loading onConfirm={vi.fn()} onCancel={vi.fn()} />,
    );

    expect(screen.getByRole('button', { name: 'Confirm' })).toBeDisabled();
    expect(screen.getByRole('button', { name: 'Cancel' })).toBeDisabled();
  });
});
