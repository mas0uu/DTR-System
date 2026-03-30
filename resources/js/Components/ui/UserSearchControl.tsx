import { SearchOutlined } from '@ant-design/icons';
import { Button, Input, Space } from 'antd';

type UserSearchControlProps = {
    value: string;
    onChange: (value: string) => void;
    placeholder?: string;
    width?: number | string;
};

export default function UserSearchControl({
    value,
    onChange,
    placeholder = 'Search user',
    width = 250,
}: UserSearchControlProps) {
    return (
        <Space.Compact className="user-search-control" style={{ width }}>
            <Input
                placeholder={placeholder}
                value={value}
                onChange={(event) => onChange(event.target.value)}
            />
            <Button type="default" icon={<SearchOutlined />} aria-label={placeholder} />
        </Space.Compact>
    );
}
