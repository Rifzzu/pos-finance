import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

export function PlaceholderPage({ title, desc }: { title: string; desc: string }) {
  return (
    <Card>
      <CardHeader>
        <CardTitle>{title}</CardTitle>
      </CardHeader>
      <CardContent>
        <p className="text-sm text-muted-foreground">{desc}</p>
      </CardContent>
    </Card>
  );
}
