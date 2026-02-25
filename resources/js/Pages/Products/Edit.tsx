import { Head, Link, useForm } from '@inertiajs/react';
import { Form, Input, InputNumber, Select, Button, Card, DatePicker, Switch } from 'antd';
import { ArrowLeftOutlined } from '@ant-design/icons';
import FormItem from 'antd/es/form/FormItem';
import form from 'antd/es/form';

interface Category {
    id: number;
    name: string;
    type: string;
}

interface Product {
    id: number;
    sku: string;
    name: string;
    category_id: number;
    description: string;
    cost_price: number;
    selling_price: number;
    unit: string;
    stock_quantity: number;
    min_stock_level: number;
    storage_temp: string;
    expiry_date: string;
    brand: string;
    supplier_sku: string;
    is_active: boolean;
}

interface EditPageProps {
    product: Product;
    categories: Category[];
}

export default function Edit({ product, categories }: EditPageProps) {
    const { data, setData, put, processing, errors } = useForm({
        category_id: product.category_id,
        sku: product.sku,
        name: product.name,
        description: product.description,
        cost_price: product.cost_price,
        selling_price: product.selling_price,
        unit: product.unit,
        stock_quantity: product.stock_quantity,
        min_stock_level: product.min_stock_level,
        storage_temp: product.storage_temp,
        expiry_date: product.expiry_date,
        brand: product.brand,
        supplier_sku: product.supplier_sku,
        is_active: product.is_active,
    });

    const handleSubmit = () => {
        put(route('products.update', product.id));
    }

    return (
        <>
            <Head title="Edit Product" />
            <div style={{ marginBottom: '24px' }}>
                <Link href={route('products.index')}>
                    <Button icon={<ArrowLeftOutlined />}>Back to Products</Button>
                </Link>
            </div>
            <Card title="Edit Product">
                <Form layout="vertical" onFinish={handleSubmit}>
                    <Form.Item
                        label="Category"
                        validateStatus={errors.category_id ? 'error' : ''}
                        help={errors.category_id}
                        required
                    >
                        <Select
                            placeholder="Select category"
                            value={data.category_id || undefined}
                            onChange={(value) => setData('category_id', value)}
                            size="large"
                        >
                            {categories.map((cat) => (
                                <Select.Option key={cat.id} value={cat.id}>
                                    {cat.name}
                                </Select.Option>
                            ))}
                        </Select>
                    </Form.Item>
                    <Form.Item
                        label="SKU"
                        validateStatus={errors.sku ? 'error' : ''}
                        help={errors.sku}
                        required
                    >
                        <Input
                            value={data.sku}
                            onChange={(e) => setData('sku', e.target.value)}
                            size="large"
                        />
                    </Form.Item>
                    <Form.Item
                        label="Product Name"
                        validateStatus={errors.name ? 'error' : ''}
                        help={errors.name}
                        required
                    >
                        <Input
                            value={data.name}
                            onChange={(e) => setData('name', e.target.value)}
                            size="large"
                        />
                    </Form.Item>
                    <Form.Item
                        label="Unit"
                        validateStatus={errors.unit ? 'error' : ''}
                        help={errors.unit}
                        required
                    >
                        <Input
                            value={data.unit}
                            onChange={(e) => setData('unit', e.target.value)}
                            size="large"
                        />
                    </Form.Item>
                    <Form.Item
                        label="Stock Quantity"
                        validateStatus={errors.stock_quantity ? 'error' : ''}
                        help={errors.stock_quantity}
                        required
                    >
                        <InputNumber
                            value={data.stock_quantity}
                            onChange={(value) => setData('stock_quantity', value || 0)}
                            size="large"
                        />
                    </Form.Item>
                    <Form.Item
                        label="Minimum Stock Level"
                        validateStatus={errors.min_stock_level ? 'error' : ''}
                        help={errors.min_stock_level}
                        required
                    >
                        <InputNumber
                            value={data.min_stock_level}
                            onChange={(value) => setData('min_stock_level', value || 0)}
                            size="large"
                        />
                    </Form.Item>
                    <Form.Item
                        label="Cost Price"
                        validateStatus={errors.cost_price ? 'error' : ''}
                        help={errors.cost_price}
                        required
                    >
                        <InputNumber
                            value={data.cost_price}
                            onChange={(value) => setData('cost_price', value || 0)}
                            size="large"
                        />
                    </Form.Item>
                    <Form.Item
                        label="Selling Price"
                        validateStatus={errors.selling_price ? 'error' : ''}
                        help={errors.selling_price}
                        required
                    >
                        <InputNumber
                            value={data.selling_price}
                            onChange={(value) => setData('selling_price', value || 0)}
                            size="large"
                        />
                    </Form.Item>
                    <Form.Item>
                        <Button type="primary" htmlType="submit" loading={processing}>
                            Update Product
                        </Button>
                    </Form.Item>
                </Form>
            </Card>
        </>
    );
}