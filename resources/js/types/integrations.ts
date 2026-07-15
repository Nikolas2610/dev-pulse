export type ExternalWorkItem = {
    id: number;
    provider: 'jira' | 'bitbucket';
    external_key?: string | null;
    type: 'jira_issue' | 'pull_request';
    title: string;
    url: string;
    status: string;
    priority?: string | null;
    is_draft: boolean;
    is_blocked: boolean;
    task_count: number;
    provider_updated_at?: string | null;
    last_synced_at?: string | null;
    jira_project?: { id: number; project_key: string; name: string } | null;
    bitbucket_repository?: { id: number; full_name: string } | null;
};

export type AttachedWorkItem = {
    external_work_item_id: number;
    relation: 'worked_on' | 'planned' | 'blocker' | 'review';
};
