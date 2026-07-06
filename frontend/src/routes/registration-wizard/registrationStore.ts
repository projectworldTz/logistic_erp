import { create } from 'zustand';

export interface OwnerDraft {
  name: string;
  email: string;
  phone: string;
  password: string;
}

export interface CompanyDraft {
  name: string;
  registration_number: string;
  tax_number: string;
  country: string;
  city: string;
  address: string;
  currency: string;
  timezone: string;
  industry: string;
}

interface RegistrationState {
  step: number;
  planCode: string | null;
  owner: OwnerDraft;
  company: CompanyDraft;
  logo: File | null;
  setStep: (step: number) => void;
  setPlanCode: (code: string) => void;
  setOwner: (owner: OwnerDraft) => void;
  setCompany: (company: CompanyDraft) => void;
  setLogo: (file: File | null) => void;
  reset: () => void;
}

const emptyOwner: OwnerDraft = { name: '', email: '', phone: '', password: '' };
const emptyCompany: CompanyDraft = {
  name: '',
  registration_number: '',
  tax_number: '',
  country: '',
  city: '',
  address: '',
  currency: 'USD',
  timezone: Intl.DateTimeFormat().resolvedOptions().timeZone ?? 'UTC',
  industry: '',
};

export const useRegistrationStore = create<RegistrationState>((set) => ({
  step: 0,
  planCode: null,
  owner: emptyOwner,
  company: emptyCompany,
  logo: null,
  setStep: (step) => set({ step }),
  setPlanCode: (planCode) => set({ planCode }),
  setOwner: (owner) => set({ owner }),
  setCompany: (company) => set({ company }),
  setLogo: (logo) => set({ logo }),
  reset: () => set({ step: 0, planCode: null, owner: emptyOwner, company: emptyCompany, logo: null }),
}));
