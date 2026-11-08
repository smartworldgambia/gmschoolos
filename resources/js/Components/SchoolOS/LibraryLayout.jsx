import ModuleLayout from '@/Components/SchoolOS/ModuleLayout';

const TABS = [
    { key: 'catalogue', route: 'library.index' },
    { key: 'loans', route: 'library.loans.index' },
    { key: 'fines', route: 'library.fines.index' },
    { key: 'setup', route: 'library.setup.index' },
];

/** Library pages share one heading and tab bar. */
export default function LibraryLayout(props) {
    return <ModuleLayout ns="lib" tabs={TABS} {...props} />;
}
