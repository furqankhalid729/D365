import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, usePage, router } from '@inertiajs/react';
import { useState } from 'react';

export default function Dashboard() {
    const { credential } = usePage().props;
    const [formData, setFormData] = useState({
        client_id: credential?.client_id || "",
        client_secret: credential?.client_secret || "",
        scope: credential?.scope || "",
        grant_type: credential?.grant_type || "",
        urls: credential?.urls ? credential.urls.join(", ") : "",
        additional_info: credential?.additional_info ? credential.additional_info.join(", ") : "",
    });
    const handleChange = (e) => {
        setFormData({ ...formData, [e.target.name]: e.target.value });
    };

    const handleSubmit = (e) => {
        e.preventDefault();
        router.post("/dashboard", {
            ...formData,
            urls: formData.urls.split(",").map((url) => url.trim()), // Convert string to array
            additional_info: formData.additional_info.split(",").map((info) => info.trim()),
        });
    };

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800">
                    Dashboard
                </h2>
            }
        >
            <Head title="Dashboard" />

            <div className="py-12">
                <div className="mx-auto max-w-7xl sm:px-6 lg:px-8">
                    <div className="overflow-hidden bg-white shadow-sm sm:rounded-lg p-7">
                        {/* <div>
                            {flash.success && (
                                <div className="bg-green-100 text-green-700 p-3 rounded mb-4">
                                    {flash?.success}
                                </div>
                            )}
                        </div> */}
                        <h2 className="text-xl font-bold mb-4">OAuth Settings</h2>
                        <form onSubmit={handleSubmit} className="space-y-4">
                            <div>
                                <label className="block text-sm font-medium">Client ID</label>
                                <input
                                    type="text"
                                    name="client_id"
                                    value={formData.client_id}
                                    onChange={handleChange}
                                    className="w-full border p-2 rounded"
                                />
                            </div>
                            <div>
                                <label className="block text-sm font-medium">Client Secret</label>
                                <input
                                    type="text"
                                    name="client_secret"
                                    value={formData.client_secret}
                                    onChange={handleChange}
                                    className="w-full border p-2 rounded"
                                />
                            </div>
                            <div>
                                <label className="block text-sm font-medium">Scope</label>
                                <input
                                    type="text"
                                    name="scope"
                                    value={formData.scope}
                                    onChange={handleChange}
                                    className="w-full border p-2 rounded"
                                />
                            </div>
                            <div>
                                <label className="block text-sm font-medium">Grant Type</label>
                                <input
                                    type="text"
                                    name="grant_type"
                                    value={formData.grant_type}
                                    onChange={handleChange}
                                    className="w-full border p-2 rounded"
                                />
                            </div>
                            {/* <div>
                                <label className="block text-sm font-medium">URLs (comma-separated)</label>
                                <input
                                    type="text"
                                    name="urls"
                                    value={formData.urls}
                                    onChange={handleChange}
                                    className="w-full border p-2 rounded"
                                />
                            </div>
                            <div>
                                <label className="block text-sm font-medium">Additional Info (comma-separated)</label>
                                <input
                                    type="text"
                                    name="additional_info"
                                    value={formData.additional_info}
                                    onChange={handleChange}
                                    className="w-full border p-2 rounded"
                                />
                            </div> */}
                            <button
                                type="submit"
                                className="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600"
                            >
                                Save
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
