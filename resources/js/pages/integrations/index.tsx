import AtlassianIntegration from './atlassian';
import DiscordIntegration from './discord';
import SlackIntegration from './slack';

type Props = {
    provider: 'slack' | 'discord' | 'jira' | 'bitbucket';
    [key: string]: unknown;
};

export default function IntegrationsIndex({ provider, ...props }: Props) {
    if (provider === 'slack') {
        return <SlackIntegration {...(props as any)} />;
    }

    if (provider === 'discord') {
        return <DiscordIntegration {...(props as any)} />;
    }

    return <AtlassianIntegration provider={provider} {...(props as any)} />;
}
