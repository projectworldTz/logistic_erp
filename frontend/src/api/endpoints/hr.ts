import { api } from '../axios';
import type {
  AttendanceRecord,
  Candidate,
  Department,
  Designation,
  DisciplinaryRecord,
  Employee,
  EmployeeAsset,
  ExitRecord,
  HrDashboardSummary,
  Interview,
  JobApplication,
  JobVacancy,
  OnboardingChecklist,
  OnboardingTask,
  EmployeeContract,
  EmployeeDocument,
  EmployeeLoan,
  EmployeePayrollComponent,
  EmployeeSalary,
  EmployeeShift,
  LeaveBalance,
  LeaveRequest,
  LeaveType,
  OvertimeRequest,
  Paginated,
  PayrollComponent,
  PayrollPeriod,
  PayrollRun,
  PayrollRunEmployee,
  PayrollSettings,
  Payslip,
  PerformanceReview,
  PublicHoliday,
  SalaryAdvance,
  SalaryPayment,
  SalaryPaymentBatch,
  Shift,
  StatutoryRuleSet,
  StatutoryTaxBand,
  StatutoryContributionRule,
  Timesheet,
} from '../../types';

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

export async function fetchEmployee(id: number): Promise<Employee> {
  const { data } = await api.get<{ data: Employee }>(`/hr/employees/${id}`);
  return data.data;
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

export async function fetchEmployeeSalary(employeeId: number): Promise<EmployeeSalary> {
  const { data } = await api.get<{ data: EmployeeSalary }>(`/hr/employees/${employeeId}/salary`);
  return data.data;
}

export async function fetchDesignations(includeInactive = false): Promise<{ data: Designation[] }> {
  const { data } = await api.get<{ data: Designation[] }>('/hr/designations', { params: { include_inactive: includeInactive } });
  return data;
}

export async function createDesignation(payload: Partial<Designation>): Promise<Designation> {
  const { data } = await api.post<{ data: Designation }>('/hr/designations', payload);
  return data.data;
}

export async function updateDesignation(id: number, payload: Partial<Designation>): Promise<Designation> {
  const { data } = await api.put<{ data: Designation }>(`/hr/designations/${id}`, payload);
  return data.data;
}

export async function deleteDesignation(id: number): Promise<void> {
  await api.delete(`/hr/designations/${id}`);
}

export async function fetchEmployeeDocuments(employeeId: number): Promise<{ data: EmployeeDocument[] }> {
  const { data } = await api.get<{ data: EmployeeDocument[] }>(`/hr/employees/${employeeId}/documents`);
  return data;
}

export async function uploadEmployeeDocument(
  employeeId: number,
  payload: { document_type: string; file: File; issue_date?: string; expiry_date?: string; notes?: string },
): Promise<EmployeeDocument> {
  const form = new FormData();
  form.append('document_type', payload.document_type);
  form.append('file', payload.file);
  if (payload.issue_date) form.append('issue_date', payload.issue_date);
  if (payload.expiry_date) form.append('expiry_date', payload.expiry_date);
  if (payload.notes) form.append('notes', payload.notes);

  const { data } = await api.post<{ data: EmployeeDocument }>(`/hr/employees/${employeeId}/documents`, form, {
    headers: { 'Content-Type': 'multipart/form-data' },
  });
  return data.data;
}

export async function verifyEmployeeDocument(id: number): Promise<EmployeeDocument> {
  const { data } = await api.post<{ data: EmployeeDocument }>(`/hr/employee-documents/${id}/verify`);
  return data.data;
}

export async function rejectEmployeeDocument(id: number, reason: string): Promise<EmployeeDocument> {
  const { data } = await api.post<{ data: EmployeeDocument }>(`/hr/employee-documents/${id}/reject`, { reason });
  return data.data;
}

export async function deleteEmployeeDocument(id: number): Promise<void> {
  await api.delete(`/hr/employee-documents/${id}`);
}

export async function fetchEmployeeContracts(employeeId?: number): Promise<Paginated<EmployeeContract>> {
  const { data } = await api.get<Paginated<EmployeeContract>>('/hr/contracts', { params: { employee_id: employeeId } });
  return data;
}

export async function createEmployeeContract(payload: Partial<EmployeeContract> & { employee_id: number }): Promise<EmployeeContract> {
  const { data } = await api.post<{ data: EmployeeContract }>('/hr/contracts', payload);
  return data.data;
}

export async function updateEmployeeContract(id: number, payload: Partial<EmployeeContract>): Promise<EmployeeContract> {
  const { data } = await api.put<{ data: EmployeeContract }>(`/hr/contracts/${id}`, payload);
  return data.data;
}

export async function deleteEmployeeContract(id: number): Promise<void> {
  await api.delete(`/hr/contracts/${id}`);
}

export async function submitEmployeeContract(id: number): Promise<EmployeeContract> {
  const { data } = await api.post<{ data: EmployeeContract }>(`/hr/contracts/${id}/submit`);
  return data.data;
}

export async function approveEmployeeContract(id: number): Promise<EmployeeContract> {
  const { data } = await api.post<{ data: EmployeeContract }>(`/hr/contracts/${id}/approve`);
  return data.data;
}

export async function rejectEmployeeContract(id: number, reason: string): Promise<EmployeeContract> {
  const { data } = await api.post<{ data: EmployeeContract }>(`/hr/contracts/${id}/reject`, { reason });
  return data.data;
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

export async function fetchShifts(): Promise<{ data: Shift[] }> {
  const { data } = await api.get<{ data: Shift[] }>('/hr/shifts');
  return data;
}

export async function createShift(payload: Partial<Shift>): Promise<Shift> {
  const { data } = await api.post<{ data: Shift }>('/hr/shifts', payload);
  return data.data;
}

export async function updateShift(id: number, payload: Partial<Shift>): Promise<Shift> {
  const { data } = await api.put<{ data: Shift }>(`/hr/shifts/${id}`, payload);
  return data.data;
}

export async function deleteShift(id: number): Promise<void> {
  await api.delete(`/hr/shifts/${id}`);
}

export async function fetchEmployeeShifts(employeeId?: number): Promise<{ data: EmployeeShift[] }> {
  const { data } = await api.get<{ data: EmployeeShift[] }>('/hr/employee-shifts', { params: { employee_id: employeeId } });
  return data;
}

export async function createEmployeeShift(payload: Partial<EmployeeShift> & { employee_id: number; shift_id: number }): Promise<EmployeeShift> {
  const { data } = await api.post<{ data: EmployeeShift }>('/hr/employee-shifts', payload);
  return data.data;
}

export async function deleteEmployeeShift(id: number): Promise<void> {
  await api.delete(`/hr/employee-shifts/${id}`);
}

export async function fetchTimesheets(page = 1, employeeId?: number): Promise<Paginated<Timesheet>> {
  const { data } = await api.get<Paginated<Timesheet>>('/hr/timesheets', { params: { page, employee_id: employeeId } });
  return data;
}

export async function createTimesheet(payload: Partial<Timesheet> & { employee_id: number }): Promise<Timesheet> {
  const { data } = await api.post<{ data: Timesheet }>('/hr/timesheets', payload);
  return data.data;
}

export async function updateTimesheet(id: number, payload: Partial<Timesheet>): Promise<Timesheet> {
  const { data } = await api.put<{ data: Timesheet }>(`/hr/timesheets/${id}`, payload);
  return data.data;
}

export async function approveTimesheet(id: number): Promise<Timesheet> {
  const { data } = await api.post<{ data: Timesheet }>(`/hr/timesheets/${id}/approve`);
  return data.data;
}

export async function rejectTimesheet(id: number, reason: string): Promise<Timesheet> {
  const { data } = await api.post<{ data: Timesheet }>(`/hr/timesheets/${id}/reject`, { reason });
  return data.data;
}

export async function fetchLeaveTypes(): Promise<{ data: LeaveType[] }> {
  const { data } = await api.get<{ data: LeaveType[] }>('/hr/leave-types');
  return data;
}

export async function createLeaveType(payload: Partial<LeaveType>): Promise<LeaveType> {
  const { data } = await api.post<{ data: LeaveType }>('/hr/leave-types', payload);
  return data.data;
}

export async function updateLeaveType(id: number, payload: Partial<LeaveType>): Promise<LeaveType> {
  const { data } = await api.put<{ data: LeaveType }>(`/hr/leave-types/${id}`, payload);
  return data.data;
}

export async function deleteLeaveType(id: number): Promise<void> {
  await api.delete(`/hr/leave-types/${id}`);
}

export async function fetchLeaveBalances(employeeId?: number, year?: number): Promise<{ data: LeaveBalance[] }> {
  const { data } = await api.get<{ data: LeaveBalance[] }>('/hr/leave-balances', { params: { employee_id: employeeId, year } });
  return data;
}

export async function fetchLeaveRequests(page = 1, employeeId?: number): Promise<Paginated<LeaveRequest>> {
  const { data } = await api.get<Paginated<LeaveRequest>>('/hr/leave-requests', { params: { page, employee_id: employeeId } });
  return data;
}

export async function createLeaveRequest(payload: Partial<LeaveRequest> & { employee_id: number; leave_type_id: number }): Promise<LeaveRequest> {
  const { data } = await api.post<{ data: LeaveRequest }>('/hr/leave-requests', payload);
  return data.data;
}

export async function approveLeaveRequest(id: number): Promise<LeaveRequest> {
  const { data } = await api.post<{ data: LeaveRequest }>(`/hr/leave-requests/${id}/approve`);
  return data.data;
}

export async function rejectLeaveRequest(id: number, reason: string): Promise<LeaveRequest> {
  const { data } = await api.post<{ data: LeaveRequest }>(`/hr/leave-requests/${id}/reject`, { reason });
  return data.data;
}

export async function cancelLeaveRequest(id: number): Promise<LeaveRequest> {
  const { data } = await api.post<{ data: LeaveRequest }>(`/hr/leave-requests/${id}/cancel`);
  return data.data;
}

export async function fetchPublicHolidays(year?: number): Promise<{ data: PublicHoliday[] }> {
  const { data } = await api.get<{ data: PublicHoliday[] }>('/hr/public-holidays', { params: { year } });
  return data;
}

export async function createPublicHoliday(payload: Partial<PublicHoliday>): Promise<PublicHoliday> {
  const { data } = await api.post<{ data: PublicHoliday }>('/hr/public-holidays', payload);
  return data.data;
}

export async function deletePublicHoliday(id: number): Promise<void> {
  await api.delete(`/hr/public-holidays/${id}`);
}

export async function importAttendanceCsv(file: File): Promise<{ created: number; errors: string[] }> {
  const form = new FormData();
  form.append('file', file);
  const { data } = await api.post<{ created: number; errors: string[] }>('/reports/import/attendance', form, {
    headers: { 'Content-Type': 'multipart/form-data' },
  });
  return data;
}

export async function fetchPayrollComponents(includeInactive = false): Promise<{ data: PayrollComponent[] }> {
  const { data } = await api.get<{ data: PayrollComponent[] }>('/hr/payroll-components', { params: { include_inactive: includeInactive } });
  return data;
}

export async function createPayrollComponent(payload: Partial<PayrollComponent>): Promise<PayrollComponent> {
  const { data } = await api.post<{ data: PayrollComponent }>('/hr/payroll-components', payload);
  return data.data;
}

export async function updatePayrollComponent(id: number, payload: Partial<PayrollComponent>): Promise<PayrollComponent> {
  const { data } = await api.put<{ data: PayrollComponent }>(`/hr/payroll-components/${id}`, payload);
  return data.data;
}

export async function deletePayrollComponent(id: number): Promise<void> {
  await api.delete(`/hr/payroll-components/${id}`);
}

export async function fetchEmployeePayrollComponents(employeeId?: number): Promise<{ data: EmployeePayrollComponent[] }> {
  const { data } = await api.get<{ data: EmployeePayrollComponent[] }>('/hr/employee-payroll-components', { params: { employee_id: employeeId } });
  return data;
}

export async function createEmployeePayrollComponent(
  payload: Partial<EmployeePayrollComponent> & { employee_id: number; payroll_component_id: number },
): Promise<EmployeePayrollComponent> {
  const { data } = await api.post<{ data: EmployeePayrollComponent }>('/hr/employee-payroll-components', payload);
  return data.data;
}

export async function deleteEmployeePayrollComponent(id: number): Promise<void> {
  await api.delete(`/hr/employee-payroll-components/${id}`);
}

export async function fetchStatutoryRuleSets(): Promise<{ data: StatutoryRuleSet[] }> {
  const { data } = await api.get<{ data: StatutoryRuleSet[] }>('/hr/statutory-rule-sets');
  return data;
}

export async function fetchStatutoryRuleSet(id: number): Promise<StatutoryRuleSet> {
  const { data } = await api.get<{ data: StatutoryRuleSet }>(`/hr/statutory-rule-sets/${id}`);
  return data.data;
}

export async function createStatutoryRuleSet(payload: Partial<StatutoryRuleSet>): Promise<StatutoryRuleSet> {
  const { data } = await api.post<{ data: StatutoryRuleSet }>('/hr/statutory-rule-sets', payload);
  return data.data;
}

export async function updateStatutoryRuleSet(id: number, payload: Partial<StatutoryRuleSet>): Promise<StatutoryRuleSet> {
  const { data } = await api.put<{ data: StatutoryRuleSet }>(`/hr/statutory-rule-sets/${id}`, payload);
  return data.data;
}

export async function deleteStatutoryRuleSet(id: number): Promise<void> {
  await api.delete(`/hr/statutory-rule-sets/${id}`);
}

export async function createStatutoryTaxBand(
  ruleSetId: number,
  payload: Partial<StatutoryTaxBand>,
): Promise<StatutoryTaxBand> {
  const { data } = await api.post<{ data: StatutoryTaxBand }>(`/hr/statutory-rule-sets/${ruleSetId}/tax-bands`, payload);
  return data.data;
}

export async function deleteStatutoryTaxBand(ruleSetId: number, bandId: number): Promise<void> {
  await api.delete(`/hr/statutory-rule-sets/${ruleSetId}/tax-bands/${bandId}`);
}

export async function createStatutoryContributionRule(
  ruleSetId: number,
  payload: Partial<StatutoryContributionRule>,
): Promise<StatutoryContributionRule> {
  const { data } = await api.post<{ data: StatutoryContributionRule }>(`/hr/statutory-rule-sets/${ruleSetId}/contribution-rules`, payload);
  return data.data;
}

export async function deleteStatutoryContributionRule(ruleSetId: number, ruleId: number): Promise<void> {
  await api.delete(`/hr/statutory-rule-sets/${ruleSetId}/contribution-rules/${ruleId}`);
}

export async function fetchPayrollSettings(): Promise<PayrollSettings> {
  const { data } = await api.get<{ data: PayrollSettings }>('/hr/payroll-settings');
  return data.data;
}

export async function updatePayrollSettings(payload: Partial<PayrollSettings>): Promise<PayrollSettings> {
  const { data } = await api.put<{ data: PayrollSettings }>('/hr/payroll-settings', payload);
  return data.data;
}

export async function fetchPayrollPeriods(): Promise<{ data: PayrollPeriod[] }> {
  const { data } = await api.get<{ data: PayrollPeriod[] }>('/hr/payroll-periods');
  return data;
}

export async function fetchPayrollPeriod(id: number): Promise<PayrollPeriod> {
  const { data } = await api.get<{ data: PayrollPeriod }>(`/hr/payroll-periods/${id}`);
  return data.data;
}

export async function createPayrollPeriod(payload: Partial<PayrollPeriod>): Promise<PayrollPeriod> {
  const { data } = await api.post<{ data: PayrollPeriod }>('/hr/payroll-periods', payload);
  return data.data;
}

export async function deletePayrollPeriod(id: number): Promise<void> {
  await api.delete(`/hr/payroll-periods/${id}`);
}

export async function createPayrollRun(periodId: number, statutoryRuleSetId?: number): Promise<PayrollRun> {
  const { data } = await api.post<{ data: PayrollRun }>(`/hr/payroll-periods/${periodId}/runs`, {
    statutory_rule_set_id: statutoryRuleSetId,
  });
  return data.data;
}

export async function fetchPayrollRuns(periodId?: number): Promise<{ data: PayrollRun[] }> {
  const { data } = await api.get<{ data: PayrollRun[] }>('/hr/payroll-runs', { params: { payroll_period_id: periodId } });
  return data;
}

export async function fetchPayrollRun(id: number): Promise<PayrollRun> {
  const { data } = await api.get<{ data: PayrollRun }>(`/hr/payroll-runs/${id}`);
  return data.data;
}

export async function calculatePayrollRun(id: number): Promise<PayrollRun> {
  const { data } = await api.post<{ data: PayrollRun }>(`/hr/payroll-runs/${id}/calculate`);
  return data.data;
}

export async function submitPayrollRun(id: number): Promise<PayrollRun> {
  const { data } = await api.post<{ data: PayrollRun }>(`/hr/payroll-runs/${id}/submit`);
  return data.data;
}

export async function approvePayrollRun(id: number): Promise<PayrollRun> {
  const { data } = await api.post<{ data: PayrollRun }>(`/hr/payroll-runs/${id}/approve`);
  return data.data;
}

export async function rejectPayrollRun(id: number, reason: string): Promise<PayrollRun> {
  const { data } = await api.post<{ data: PayrollRun }>(`/hr/payroll-runs/${id}/reject`, { reason });
  return data.data;
}

export async function finalizePayrollRun(id: number): Promise<PayrollRun> {
  const { data } = await api.post<{ data: PayrollRun }>(`/hr/payroll-runs/${id}/finalize`);
  return data.data;
}

export async function updatePayrollRunEmployeeStatus(id: number, status: 'included' | 'excluded'): Promise<PayrollRunEmployee> {
  const { data } = await api.put<{ data: PayrollRunEmployee }>(`/hr/payroll-run-employees/${id}`, { status });
  return data.data;
}

export async function fetchLoans(employeeId?: number): Promise<Paginated<EmployeeLoan>> {
  const { data } = await api.get<Paginated<EmployeeLoan>>('/hr/loans', { params: { employee_id: employeeId } });
  return data;
}

export async function fetchLoan(id: number): Promise<EmployeeLoan> {
  const { data } = await api.get<{ data: EmployeeLoan }>(`/hr/loans/${id}`);
  return data.data;
}

export async function createLoan(payload: Partial<EmployeeLoan> & { employee_id: number }): Promise<EmployeeLoan> {
  const { data } = await api.post<{ data: EmployeeLoan }>('/hr/loans', payload);
  return data.data;
}

export async function deleteLoan(id: number): Promise<void> {
  await api.delete(`/hr/loans/${id}`);
}

export async function submitLoan(id: number): Promise<EmployeeLoan> {
  const { data } = await api.post<{ data: EmployeeLoan }>(`/hr/loans/${id}/submit`);
  return data.data;
}

export async function approveLoan(id: number): Promise<EmployeeLoan> {
  const { data } = await api.post<{ data: EmployeeLoan }>(`/hr/loans/${id}/approve`);
  return data.data;
}

export async function rejectLoan(id: number, reason: string): Promise<EmployeeLoan> {
  const { data } = await api.post<{ data: EmployeeLoan }>(`/hr/loans/${id}/reject`, { reason });
  return data.data;
}

export async function fetchSalaryAdvances(employeeId?: number): Promise<Paginated<SalaryAdvance>> {
  const { data } = await api.get<Paginated<SalaryAdvance>>('/hr/salary-advances', { params: { employee_id: employeeId } });
  return data;
}

export async function createSalaryAdvance(payload: Partial<SalaryAdvance> & { employee_id: number }): Promise<SalaryAdvance> {
  const { data } = await api.post<{ data: SalaryAdvance }>('/hr/salary-advances', payload);
  return data.data;
}

export async function deleteSalaryAdvance(id: number): Promise<void> {
  await api.delete(`/hr/salary-advances/${id}`);
}

export async function submitSalaryAdvance(id: number): Promise<SalaryAdvance> {
  const { data } = await api.post<{ data: SalaryAdvance }>(`/hr/salary-advances/${id}/submit`);
  return data.data;
}

export async function approveSalaryAdvance(id: number): Promise<SalaryAdvance> {
  const { data } = await api.post<{ data: SalaryAdvance }>(`/hr/salary-advances/${id}/approve`);
  return data.data;
}

export async function rejectSalaryAdvance(id: number, reason: string): Promise<SalaryAdvance> {
  const { data } = await api.post<{ data: SalaryAdvance }>(`/hr/salary-advances/${id}/reject`, { reason });
  return data.data;
}

export async function fetchOvertimeRequests(employeeId?: number): Promise<Paginated<OvertimeRequest>> {
  const { data } = await api.get<Paginated<OvertimeRequest>>('/hr/overtime-requests', { params: { employee_id: employeeId } });
  return data;
}

export async function createOvertimeRequest(payload: Partial<OvertimeRequest> & { employee_id: number }): Promise<OvertimeRequest> {
  const { data } = await api.post<{ data: OvertimeRequest }>('/hr/overtime-requests', payload);
  return data.data;
}

export async function deleteOvertimeRequest(id: number): Promise<void> {
  await api.delete(`/hr/overtime-requests/${id}`);
}

export async function approveOvertimeRequest(id: number): Promise<OvertimeRequest> {
  const { data } = await api.post<{ data: OvertimeRequest }>(`/hr/overtime-requests/${id}/approve`);
  return data.data;
}

export async function rejectOvertimeRequest(id: number, reason: string): Promise<OvertimeRequest> {
  const { data } = await api.post<{ data: OvertimeRequest }>(`/hr/overtime-requests/${id}/reject`, { reason });
  return data.data;
}

export async function postPayrollRunToAccounting(id: number): Promise<PayrollRun> {
  const { data } = await api.post<{ data: PayrollRun }>(`/hr/payroll-runs/${id}/post-to-accounting`);
  return data.data;
}

export async function fetchPayslips(employeeId?: number): Promise<Paginated<Payslip>> {
  const { data } = await api.get<Paginated<Payslip>>('/hr/payslips', { params: { employee_id: employeeId } });
  return data;
}

export async function downloadPayslipPdf(id: number): Promise<Blob> {
  const { data } = await api.get(`/hr/payslips/${id}/pdf`, { responseType: 'blob' });
  return data;
}

export async function generateSalaryPaymentBatch(runId: number): Promise<SalaryPaymentBatch> {
  const { data } = await api.post<{ data: SalaryPaymentBatch }>(`/hr/payroll-runs/${runId}/salary-payments`);
  return data.data;
}

export async function fetchSalaryPaymentBatch(id: number): Promise<SalaryPaymentBatch> {
  const { data } = await api.get<{ data: SalaryPaymentBatch }>(`/hr/salary-payment-batches/${id}`);
  return data.data;
}

export async function updateSalaryPayment(id: number, status: 'paid' | 'failed', reference?: string): Promise<SalaryPayment> {
  const { data } = await api.put<{ data: SalaryPayment }>(`/hr/salary-payments/${id}`, { status, reference });
  return data.data;
}

export async function downloadSalaryPaymentBatchCsv(id: number): Promise<Blob> {
  const { data } = await api.get(`/hr/salary-payment-batches/${id}/export`, { responseType: 'blob' });
  return data;
}

export async function fetchPerformanceReviews(employeeId?: number): Promise<Paginated<PerformanceReview>> {
  const { data } = await api.get<Paginated<PerformanceReview>>('/hr/performance-reviews', { params: { employee_id: employeeId } });
  return data;
}

export async function createPerformanceReview(payload: Partial<PerformanceReview> & { employee_id: number }): Promise<PerformanceReview> {
  const { data } = await api.post<{ data: PerformanceReview }>('/hr/performance-reviews', payload);
  return data.data;
}

export async function submitPerformanceReview(id: number): Promise<PerformanceReview> {
  const { data } = await api.post<{ data: PerformanceReview }>(`/hr/performance-reviews/${id}/submit`);
  return data.data;
}

export async function acknowledgePerformanceReview(id: number, employeeComments?: string): Promise<PerformanceReview> {
  const { data } = await api.post<{ data: PerformanceReview }>(`/hr/performance-reviews/${id}/acknowledge`, { employee_comments: employeeComments });
  return data.data;
}

export async function deletePerformanceReview(id: number): Promise<void> {
  await api.delete(`/hr/performance-reviews/${id}`);
}

export async function fetchDisciplinaryRecords(employeeId?: number): Promise<Paginated<DisciplinaryRecord>> {
  const { data } = await api.get<Paginated<DisciplinaryRecord>>('/hr/disciplinary-records', { params: { employee_id: employeeId } });
  return data;
}

export async function createDisciplinaryRecord(payload: Partial<DisciplinaryRecord> & { employee_id: number }): Promise<DisciplinaryRecord> {
  const { data } = await api.post<{ data: DisciplinaryRecord }>('/hr/disciplinary-records', payload);
  return data.data;
}

export async function acknowledgeDisciplinaryRecord(id: number, employeeResponse?: string): Promise<DisciplinaryRecord> {
  const { data } = await api.post<{ data: DisciplinaryRecord }>(`/hr/disciplinary-records/${id}/acknowledge`, { employee_response: employeeResponse });
  return data.data;
}

export async function resolveDisciplinaryRecord(id: number): Promise<DisciplinaryRecord> {
  const { data } = await api.post<{ data: DisciplinaryRecord }>(`/hr/disciplinary-records/${id}/resolve`);
  return data.data;
}

export async function deleteDisciplinaryRecord(id: number): Promise<void> {
  await api.delete(`/hr/disciplinary-records/${id}`);
}

export async function fetchEmployeeAssets(employeeId?: number): Promise<Paginated<EmployeeAsset>> {
  const { data } = await api.get<Paginated<EmployeeAsset>>('/hr/employee-assets', { params: { employee_id: employeeId } });
  return data;
}

export async function createEmployeeAsset(payload: Partial<EmployeeAsset> & { employee_id: number }): Promise<EmployeeAsset> {
  const { data } = await api.post<{ data: EmployeeAsset }>('/hr/employee-assets', payload);
  return data.data;
}

export async function returnEmployeeAsset(id: number, payload: { return_date: string; condition_at_return?: string; status: 'returned' | 'lost' | 'damaged' }): Promise<EmployeeAsset> {
  const { data } = await api.post<{ data: EmployeeAsset }>(`/hr/employee-assets/${id}/return`, payload);
  return data.data;
}

export async function deleteEmployeeAsset(id: number): Promise<void> {
  await api.delete(`/hr/employee-assets/${id}`);
}

export async function fetchExitRecords(): Promise<Paginated<ExitRecord>> {
  const { data } = await api.get<Paginated<ExitRecord>>('/hr/exit-records');
  return data;
}

export async function createExitRecord(payload: Partial<ExitRecord> & { employee_id: number }): Promise<ExitRecord> {
  const { data } = await api.post<{ data: ExitRecord }>('/hr/exit-records', payload);
  return data.data;
}

export async function updateExitRecord(id: number, payload: Partial<ExitRecord>): Promise<ExitRecord> {
  const { data } = await api.put<{ data: ExitRecord }>(`/hr/exit-records/${id}`, payload);
  return data.data;
}

export async function completeExitRecord(id: number): Promise<ExitRecord> {
  const { data } = await api.post<{ data: ExitRecord }>(`/hr/exit-records/${id}/complete`);
  return data.data;
}

export async function fetchJobVacancies(status?: string): Promise<Paginated<JobVacancy>> {
  const { data } = await api.get<Paginated<JobVacancy>>('/hr/job-vacancies', { params: { status } });
  return data;
}

export async function fetchJobVacancy(id: number): Promise<JobVacancy> {
  const { data } = await api.get<{ data: JobVacancy }>(`/hr/job-vacancies/${id}`);
  return data.data;
}

export async function createJobVacancy(payload: Partial<JobVacancy>): Promise<JobVacancy> {
  const { data } = await api.post<{ data: JobVacancy }>('/hr/job-vacancies', payload);
  return data.data;
}

export async function closeJobVacancy(id: number): Promise<JobVacancy> {
  const { data } = await api.post<{ data: JobVacancy }>(`/hr/job-vacancies/${id}/close`);
  return data.data;
}

export async function deleteJobVacancy(id: number): Promise<void> {
  await api.delete(`/hr/job-vacancies/${id}`);
}

export async function fetchCandidates(search?: string): Promise<Paginated<Candidate>> {
  const { data } = await api.get<Paginated<Candidate>>('/hr/candidates', { params: { search } });
  return data;
}

export async function createCandidate(payload: Partial<Candidate>): Promise<Candidate> {
  const { data } = await api.post<{ data: Candidate }>('/hr/candidates', payload);
  return data.data;
}

export async function deleteCandidate(id: number): Promise<void> {
  await api.delete(`/hr/candidates/${id}`);
}

export async function fetchJobApplications(jobVacancyId?: number): Promise<Paginated<JobApplication>> {
  const { data } = await api.get<Paginated<JobApplication>>('/hr/job-applications', { params: { job_vacancy_id: jobVacancyId } });
  return data;
}

export async function fetchJobApplication(id: number): Promise<JobApplication> {
  const { data } = await api.get<{ data: JobApplication }>(`/hr/job-applications/${id}`);
  return data.data;
}

export async function createJobApplication(payload: { job_vacancy_id: number; candidate_id: number; applied_date: string }): Promise<JobApplication> {
  const { data } = await api.post<{ data: JobApplication }>('/hr/job-applications', payload);
  return data.data;
}

export async function updateJobApplicationStatus(id: number, status: string, notes?: string): Promise<JobApplication> {
  const { data } = await api.put<{ data: JobApplication }>(`/hr/job-applications/${id}/status`, { status, notes });
  return data.data;
}

export async function hireJobApplication(id: number, payload?: { employment_type?: string; hire_date?: string }): Promise<{ application: JobApplication; employee_id: number }> {
  const { data } = await api.post<{ data: { application: JobApplication; employee_id: number } }>(`/hr/job-applications/${id}/hire`, payload);
  return data.data;
}

export async function deleteJobApplication(id: number): Promise<void> {
  await api.delete(`/hr/job-applications/${id}`);
}

export async function createInterview(payload: { job_application_id: number; interviewer_id?: number; scheduled_at: string; mode?: string; location?: string }): Promise<Interview> {
  const { data } = await api.post<{ data: Interview }>('/hr/interviews', payload);
  return data.data;
}

export async function completeInterview(id: number, payload: { status: string; feedback?: string; rating?: number }): Promise<Interview> {
  const { data } = await api.post<{ data: Interview }>(`/hr/interviews/${id}/complete`, payload);
  return data.data;
}

export async function fetchOnboardingChecklists(status?: string): Promise<Paginated<OnboardingChecklist>> {
  const { data } = await api.get<Paginated<OnboardingChecklist>>('/hr/onboarding-checklists', { params: { status } });
  return data;
}

export async function fetchOnboardingChecklist(id: number): Promise<OnboardingChecklist> {
  const { data } = await api.get<{ data: OnboardingChecklist }>(`/hr/onboarding-checklists/${id}`);
  return data.data;
}

export async function createOnboardingTask(checklistId: number, payload: { title: string; description?: string; due_date?: string }): Promise<OnboardingTask> {
  const { data } = await api.post<{ data: OnboardingTask }>(`/hr/onboarding-checklists/${checklistId}/tasks`, payload);
  return data.data;
}

export async function toggleOnboardingTask(id: number): Promise<OnboardingTask> {
  const { data } = await api.post<{ data: OnboardingTask }>(`/hr/onboarding-tasks/${id}/toggle`);
  return data.data;
}

export async function deleteOnboardingTask(id: number): Promise<void> {
  await api.delete(`/hr/onboarding-tasks/${id}`);
}

export async function fetchHrDashboard(): Promise<HrDashboardSummary> {
  const { data } = await api.get<HrDashboardSummary>('/hr/dashboard');
  return data;
}

export async function fetchMyProfile(): Promise<Employee> {
  const { data } = await api.get<{ data: Employee }>('/hr/my/profile');
  return data.data;
}

export async function fetchMyAttendance(page = 1): Promise<Paginated<AttendanceRecord>> {
  const { data } = await api.get<Paginated<AttendanceRecord>>('/hr/my/attendance', { params: { page } });
  return data;
}

export async function fetchMyLeaveTypes(): Promise<{ data: LeaveType[] }> {
  const { data } = await api.get<{ data: LeaveType[] }>('/hr/my/leave-types');
  return data;
}

export async function fetchMyLeaveBalances(year?: number): Promise<{ data: LeaveBalance[] }> {
  const { data } = await api.get<{ data: LeaveBalance[] }>('/hr/my/leave-balances', { params: { year } });
  return data;
}

export async function fetchMyLeaveRequests(): Promise<Paginated<LeaveRequest>> {
  const { data } = await api.get<Paginated<LeaveRequest>>('/hr/my/leave-requests');
  return data;
}

export async function createMyLeaveRequest(payload: { leave_type_id: number; start_date: string; end_date: string; half_day?: boolean; reason?: string }): Promise<LeaveRequest> {
  const { data } = await api.post<{ data: LeaveRequest }>('/hr/my/leave-requests', payload);
  return data.data;
}

export async function cancelMyLeaveRequest(id: number): Promise<LeaveRequest> {
  const { data } = await api.post<{ data: LeaveRequest }>(`/hr/my/leave-requests/${id}/cancel`);
  return data.data;
}

export async function fetchMyAssets(): Promise<Paginated<EmployeeAsset>> {
  const { data } = await api.get<Paginated<EmployeeAsset>>('/hr/my/assets');
  return data;
}
