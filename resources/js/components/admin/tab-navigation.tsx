import { cn } from '@/lib/utils';
import { Button } from '@/components/ui/button';

export type Tab = {
    key: string;
    label: string;
};

type TabNavigationProps = {
    tabs: Tab[];
    activeTab: string;
    onTabChange: (key: string) => void;
};

export function TabNavigation({ tabs, activeTab, onTabChange }: TabNavigationProps) {
    return (
        <div className="border-b">
            <nav className="-mb-px flex gap-2 overflow-x-auto" aria-label="Tabs">
                {tabs.map((tab) => (
                    <Button
                        key={tab.key}
                        variant="ghost"
                        size="sm"
                        className={cn(
                            'rounded-none border-b-2 border-transparent',
                            activeTab === tab.key && 'border-primary text-primary',
                        )}
                        onClick={() => onTabChange(tab.key)}
                    >
                        {tab.label}
                    </Button>
                ))}
            </nav>
        </div>
    );
}
