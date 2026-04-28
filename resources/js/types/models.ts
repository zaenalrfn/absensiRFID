export interface Device {
    id: number;
    device_code: string;
    device_name: string;
    location: string | null;
    last_seen_at: string | null;
    last_ip: string | null;
    is_online: boolean;
}

export interface Schedule {
    id: number;
    name: string;
    start_time: string;
    end_time: string;
    created_at: string;
    updated_at: string;
}

export interface AttendanceRecord {
    id: number;
    user_name: string;
    uid: string;
    status: 'masuk' | 'pulang';
    schedule: string;
    device_id: string;
    timestamp: string;
}

export interface DashboardStats {
    total_today: number;
    masuk: number;
    pulang: number;
    active_devices: number;
}

export interface Paginated<T> {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    links: PaginationLink[];
}

export interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}
