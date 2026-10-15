import * as TabsPrimitive from '@radix-ui/react-tabs';
import type { ComponentProps } from 'react';
import { cn } from '@/lib/utils';

function Tabs({ className, ...props }: ComponentProps<typeof TabsPrimitive.Root>) {
    return <TabsPrimitive.Root data-slot="tabs" className={cn('flex flex-col gap-2', className)} {...props} />;
}
function TabsList({ className, ...props }: ComponentProps<typeof TabsPrimitive.List>) {
    return <TabsPrimitive.List data-slot="tabs-list" className={cn('inline-flex w-fit max-w-full items-center gap-1 rounded-lg bg-muted p-1 text-muted-foreground', className)} {...props} />;
}
function TabsTrigger({ className, ...props }: ComponentProps<typeof TabsPrimitive.Trigger>) {
    return <TabsPrimitive.Trigger data-slot="tabs-trigger" className={cn('inline-flex items-center justify-center rounded-md px-3 py-2 text-sm font-medium whitespace-nowrap transition-colors focus-visible:outline-2 focus-visible:outline-ring disabled:pointer-events-none disabled:opacity-50 data-[state=active]:bg-background data-[state=active]:text-foreground data-[state=active]:shadow-sm', className)} {...props} />;
}
function TabsContent({ className, ...props }: ComponentProps<typeof TabsPrimitive.Content>) {
    return <TabsPrimitive.Content data-slot="tabs-content" className={cn('mt-6 flex-1 outline-none focus-visible:outline-2 focus-visible:outline-ring', className)} {...props} />;
}
export { Tabs, TabsList, TabsTrigger, TabsContent };
