import type { ApiConfig, ApiCourse, ApiTrialAccess } from '../types';
import { apiPost, fetchAllPages } from './apiClient';

type CreateTrialAccessInput = {
  studentName: string;
  studentEmail?: string;
  studentPhone?: string;
  courseId: number;
  accessDate: string;
};

export type CreatedTrialAccess = {
  id: number;
  student_id: number;
  course_id: number;
  course_name: string;
  student_name: string;
  student_email: string;
  portal_login: string;
  portal_password: string;
  access_date: string;
};

export async function loadPublishedCoursesForTrialAccess(config: ApiConfig): Promise<ApiCourse[]> {
  const response = await fetchAllPages<ApiCourse>(config, 'courses', { status: 'published' });

  return response.rows
    .filter((course) => Number(course.id) > 0 && String(course.name ?? '').trim() !== '')
    .sort((left, right) => left.name.localeCompare(right.name, 'pt-BR'));
}

export async function listTrialAccessesForMobile(config: ApiConfig): Promise<ApiTrialAccess[]> {
  const response = await fetchAllPages<ApiTrialAccess>(config, 'trial_accesses');
  return response.rows;
}

export async function createTrialAccessFromMobile(
  config: ApiConfig,
  input: CreateTrialAccessInput
): Promise<CreatedTrialAccess> {
  const payload = {
    student_name: input.studentName.trim(),
    student_email: (input.studentEmail ?? '').trim(),
    student_phone: (input.studentPhone ?? '').trim(),
    course_id: input.courseId,
    access_date: input.accessDate.trim(),
  };

  const response = await apiPost<CreatedTrialAccess, typeof payload>(config, 'trial_accesses', payload);
  return response.data;
}
