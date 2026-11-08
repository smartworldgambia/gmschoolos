import ModuleLayout from '@/Components/SchoolOS/ModuleLayout';

const TABS = [
    { key: 'items', route: 'inventory.items.index' },
    { key: 'stock', route: 'inventory.stock.index' },
    { key: 'assets', route: 'inventory.assets.index' },
    { key: 'setup', route: 'inventory.setup.index' },
];

export default function InventoryLayout(props) {
    return <ModuleLayout ns="inv" tabs={TABS} {...props} />;
}
