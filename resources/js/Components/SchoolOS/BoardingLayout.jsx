import ModuleLayout from '@/Components/SchoolOS/ModuleLayout';

const TABS = [
    { key: 'overview', route: 'boarding.index' },
    { key: 'attendance', route: 'boarding.attendance.index' },
    { key: 'welfare', route: 'boarding.welfare.index' },
];

export default function BoardingLayout(props) {
    return <ModuleLayout ns="bd" tabs={TABS} {...props} />;
}
