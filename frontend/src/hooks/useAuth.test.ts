import { useAuthStore } from './useAuth';
import type { User } from '../types';

const mockUser: User = {
  id: 1,
  tenant_id: 8,
  branch_id: null,
  customer_id: null,
  name: 'Jane Doe',
  email: 'jane@acme.test',
  phone: null,
  is_super_admin: false,
  status: 'active',
  two_factor_enabled: false,
  roles: ['Company Owner'],
  permissions: ['crm.customers.view'],
};

describe('useAuthStore', () => {
  beforeEach(() => {
    useAuthStore.setState({ token: null, user: null });
    localStorage.clear();
  });

  it('starts with no token and no user', () => {
    const state = useAuthStore.getState();
    expect(state.token).toBeNull();
    expect(state.user).toBeNull();
  });

  it('setSession stores the token and user', () => {
    useAuthStore.getState().setSession('abc123', mockUser);

    const state = useAuthStore.getState();
    expect(state.token).toBe('abc123');
    expect(state.user).toEqual(mockUser);
  });

  it('updateUser replaces the user without touching the token', () => {
    useAuthStore.getState().setSession('abc123', mockUser);
    const updated: User = { ...mockUser, name: 'Jane Updated' };

    useAuthStore.getState().updateUser(updated);

    const state = useAuthStore.getState();
    expect(state.token).toBe('abc123');
    expect(state.user?.name).toBe('Jane Updated');
  });

  it('logout clears both token and user', () => {
    useAuthStore.getState().setSession('abc123', mockUser);

    useAuthStore.getState().logout();

    const state = useAuthStore.getState();
    expect(state.token).toBeNull();
    expect(state.user).toBeNull();
  });

  it('persists under the expected localStorage key', () => {
    useAuthStore.getState().setSession('abc123', mockUser);

    const raw = localStorage.getItem('logistics-erp-auth');
    expect(raw).not.toBeNull();

    const parsed = JSON.parse(raw!);
    expect(parsed.state.token).toBe('abc123');
    expect(parsed.state.user.email).toBe('jane@acme.test');
  });
});
