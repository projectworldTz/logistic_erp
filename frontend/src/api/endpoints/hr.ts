import { api } from '../axios';
import type { AttendanceRecord, Department, Employee, Paginated } from '../../types';

export async function fetchDepartments(): Promise<{ data: Department[] }> {
  const { data } = await api.get<{ data: Department[] }>('/hr/departments');
  return data;
}

export async function createDepartment(payload: Partial<Department>): Promise<Department> {
  const { data } = await api.post<{ data: Department }>('/hr/departments', payload);
  return data.data;
}

export async function updateDepartment(id: number, payload: Partial<Department>): Promise<Department> {
  const { data } = await api.put<{ data: Department }>(`/hr/departments/${id}`, payload);
  return data.data;
}

export async function deleteDepartment(id: number): Promise<void> {
  await api.delete(`/hr/departments/${id}`);
}

export async function fetchEmployees(page = 1, status?: string): Promise<Paginated<Employee>> {
  const { data } = await api.get<Paginated<Employee>>('/hr/employees', { params: { page, status } });
  return data;
}

export async function createEmployee(payload: Partial<Employee>): Promise<Employee> {
  const { data } = await api.post<{ data: Employee }>('/hr/employees', payload);
  return data.data;
}

export async function updateEmployee(id: number, payload: Partial<Employee>): Promise<Employee> {
  const { data } = await api.put<{ data: Employee }>(`/hr/employees/${id}`, payload);
  return data.data;
}

export async function deleteEmployee(id: number): Promise<void> {
  await api.delete(`/hr/employees/${id}`);
}

export async function fetchAttendanceRecords(page = 1, employeeId?: number): Promise<Paginated<AttendanceRecord>> {
  const { data } = await api.get<Paginated<AttendanceRecord>>('/hr/attendance', { params: { page, employee_id: employeeId } });
  return data;
}

export async function createAttendanceRecord(payload: Partial<AttendanceRecord>): Promise<AttendanceRecord> {
  const { data } = await api.post<{ data: AttendanceRecord }>('/hr/attendance', payload);
  return data.data;
}

export async function updateAttendanceRecord(id: number, payload: Partial<AttendanceRecord>): Promise<AttendanceRecord> {
  const { data } = await api.put<{ data: AttendanceRecord }>(`/hr/attendance/${id}`, payload);
  return data.data;
}

export async function deleteAttendanceRecord(id: number): Promise<void> {
  await api.delete(`/hr/attendance/${id}`);
}
