import ModuleLayout from '@/Components/SchoolOS/ModuleLayout';

export default function SafeguardingLayout({ canManage = true, ...props }) {
    const tabs = canManage
        ? [
              { key: 'cases', route: 'safeguarding.index' },
              { key: 'report', route: 'safeguarding.report' },
          ]
        : [{ key: 'report', route: 'safeguarding.report' }];

    return <ModuleLayout ns="sg" tabs={tabs} {...props} />;
}
