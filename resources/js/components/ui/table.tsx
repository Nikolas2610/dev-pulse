import * as React from 'react';
import { cn } from '@/lib/utils';

function Table({ className, ...props }: React.ComponentProps<'table'>) {
    return <div className="relative w-full overflow-x-auto"><table className={cn('w-full caption-bottom text-sm', className)} {...props} /></div>;
}
function TableHeader(props: React.ComponentProps<'thead'>) { return <thead className={cn('[&_tr]:border-b', props.className)} {...props} />; }
function TableBody(props: React.ComponentProps<'tbody'>) { return <tbody className={cn('[&_tr:last-child]:border-0', props.className)} {...props} />; }
function TableRow(props: React.ComponentProps<'tr'>) { return <tr className={cn('border-b transition-colors hover:bg-muted/50', props.className)} {...props} />; }
function TableHead(props: React.ComponentProps<'th'>) { return <th className={cn('h-10 px-3 text-left align-middle font-medium whitespace-nowrap text-muted-foreground', props.className)} {...props} />; }
function TableCell(props: React.ComponentProps<'td'>) { return <td className={cn('p-3 align-middle whitespace-nowrap', props.className)} {...props} />; }

export { Table, TableBody, TableCell, TableHead, TableHeader, TableRow };
