import * as React from 'react';
import { Slot } from '@radix-ui/react-slot';
import { Controller, FormProvider, useFormContext, type ControllerProps, type FieldPath, type FieldValues } from 'react-hook-form';
import { cn } from '@/lib/utils';
import { Label } from '@/components/ui/label';

const Form = FormProvider;

type FormFieldContextValue<TFieldValues extends FieldValues = FieldValues, TName extends FieldPath<TFieldValues> = FieldPath<TFieldValues>> = {
  name: TName;
};

const FormFieldContext = React.createContext<FormFieldContextValue>({} as FormFieldContextValue);

const FormField = <TFieldValues extends FieldValues = FieldValues, TName extends FieldPath<TFieldValues> = FieldPath<TFieldValues>>({
  ...props
}: ControllerProps<TFieldValues, TName>) => (
  <FormFieldContext.Provider value={{ name: props.name }}>
    <Controller {...props} />
  </FormFieldContext.Provider>
);

const useFormField = () => {
  const fieldContext = React.useContext(FormFieldContext);
  const { getFieldState, formState } = useFormContext();
  const fieldState = getFieldState(fieldContext.name, formState);
  return { name: fieldContext.name, ...fieldState };
};

const FormItem = React.forwardRef<HTMLDivElement, React.HTMLAttributes<HTMLDivElement>>(({ className, ...props }, ref) => (
  <div ref={ref} className={cn('space-y-2', className)} {...props} />
));
FormItem.displayName = 'FormItem';

const FormLabel = React.forwardRef<React.ElementRef<typeof Label>, React.ComponentPropsWithoutRef<typeof Label>>(
  ({ className, ...props }, ref) => <Label ref={ref} className={className} {...props} />,
);
FormLabel.displayName = 'FormLabel';

const FormControl = React.forwardRef<React.ElementRef<typeof Slot>, React.ComponentPropsWithoutRef<typeof Slot>>(
  ({ ...props }, ref) => <Slot ref={ref} {...props} />,
);
FormControl.displayName = 'FormControl';

const FormMessage = React.forwardRef<HTMLParagraphElement, React.HTMLAttributes<HTMLParagraphElement>>(
  ({ className, children, ...props }, ref) => {
    const { error } = useFormField();
    const body = error ? String(error?.message) : children;
    if (!body) return null;
    return (
      <p ref={ref} className={cn('text-[0.8rem] font-medium text-destructive', className)} {...props}>
        {body}
      </p>
    );
  },
);
FormMessage.displayName = 'FormMessage';

export { useFormField, Form, FormItem, FormLabel, FormControl, FormMessage, FormField };
