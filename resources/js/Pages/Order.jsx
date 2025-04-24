import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';
import React, { useMemo, useState } from 'react';
import { themeBalham } from 'ag-grid-community';
import { AgGridReact } from 'ag-grid-react';
import { AllCommunityModule, ModuleRegistry } from 'ag-grid-community'; 

// Register all Community features
ModuleRegistry.registerModules([AllCommunityModule]);
import SendOrderButton from '@/Components/SendOrderButton';

export default function Orders({ orders }) {
    console.log(orders)
    const myTheme = themeBalham.withParams({ accentColor: 'red' });
    const [rowData] = useState(orders);
    const columnDefs = useMemo(() => [
        { field: 'id', headerName: 'ID' },
        { field: 'orderId', headerName: 'Order ID' },
        { field: 'email', headerName: 'Email' },
        { field: 'status', headerName: 'Status' },
        { field: 'salesID', headerName: 'Sales Number' },
        {
            field: 'created_at',
            headerName: 'Created At',
            valueFormatter: params => {
                const date = new Date(params.value);
                return date.toLocaleDateString(); // e.g., "4/17/2025"
            }
        },
        {
            headerName: 'Action',
            field: 'action',
            cellRenderer: params => <SendOrderButton data={params.data} />,
        },
    ], []);

    const defaultColDef = useMemo(() => ({
        sortable: true,
        filter: true,
        resizable: true,
    }), []);

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800">
                    Orders
                </h2>
            }
        >
            <Head title="Orders" />
            <div className="ag-theme-alpine mx-auto max-w-7xl px-4 sm:px-6 lg:px-8" style={{ height: 500, width: '100%' }}>
                <AgGridReact
                    theme='legacy'
                    rowData={rowData}
                    columnDefs={columnDefs}
                    defaultColDef={defaultColDef}
                    pagination={true}
                    paginationPageSize={50}
                />
            </div>

        </AuthenticatedLayout>
    );
}
