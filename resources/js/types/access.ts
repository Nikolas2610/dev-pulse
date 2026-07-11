export type AccessCapabilities = {
    viewDashboard: boolean;
    viewStandups: boolean;
    manageTemplates: boolean;
    manageSchedules: boolean;
    manageIntegrations: boolean;
    viewTeam: boolean;
    manageTeam: boolean;
    manageWorkspaces: boolean;
    manageOrganization: boolean;
    manageWorkspace: boolean;
};

export type Access = {
    organizationRole: 'owner' | 'admin' | 'manager' | 'member' | null;
    workspaceRole: 'manager' | 'member' | null;
    capabilities: AccessCapabilities;
};
