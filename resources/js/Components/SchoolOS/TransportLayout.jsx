import ModuleLayout from '@/Components/SchoolOS/ModuleLayout';

const TABS = [
    { key: 'overview', route: 'transport.index' },
    { key: 'attendance', route: 'transport.attendance.index' },
];

export default function TransportLayout(props) {
    return <ModuleLayout ns="tr" tabs={TABS} {...props} />;
}
