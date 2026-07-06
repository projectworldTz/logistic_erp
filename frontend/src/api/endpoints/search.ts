import { api } from '../axios';

export interface SearchResultItem {
  id: number;
  label: string;
  path: string;
}

export type SearchResults = Record<string, SearchResultItem[]>;

export async function searchAll(q: string): Promise<SearchResults> {
  const { data } = await api.get<{ data: SearchResults }>('/search', { params: { q } });
  return data.data;
}
