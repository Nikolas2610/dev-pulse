import { motion, useReducedMotion } from 'motion/react';
import type { HTMLMotionProps, Variants } from 'motion/react';
import type { ReactNode } from 'react';

const easeOut = [0.23, 1, 0.32, 1] as const;

const containerVariants: Variants = {
    hidden: {},
    visible: {
        transition: {
            delayChildren: 0.05,
            staggerChildren: 0.07,
        },
    },
};

const itemVariants: Variants = {
    hidden: {
        opacity: 0,
        transform: 'translateY(22px)',
    },
    visible: {
        opacity: 1,
        transform: 'translateY(0px)',
        transition: {
            duration: 0.65,
            ease: easeOut,
        },
    },
};

type MotionProps = Omit<HTMLMotionProps<'div'>, 'children'> & {
    children: ReactNode;
};

export function MotionHero({ children, className, ...props }: MotionProps) {
    const reduceMotion = useReducedMotion();

    return (
        <motion.div
            className={className}
            data-motion="hero"
            {...props}
            variants={reduceMotion ? undefined : containerVariants}
            initial={reduceMotion ? false : 'hidden'}
            animate={reduceMotion ? undefined : 'visible'}
        >
            {children}
        </motion.div>
    );
}

export function MotionHeroItem({ children, className, ...props }: MotionProps) {
    return (
        <motion.div className={className} variants={itemVariants} {...props}>
            {children}
        </motion.div>
    );
}

export function MotionReveal({ children, className, ...props }: MotionProps) {
    const reduceMotion = useReducedMotion();

    return (
        <motion.div
            className={className}
            data-motion="reveal"
            {...props}
            initial={
                reduceMotion
                    ? { opacity: 0 }
                    : { opacity: 0, transform: 'translateY(24px)' }
            }
            whileInView={{ opacity: 1, transform: 'translateY(0px)' }}
            viewport={{ once: true, amount: 0.22 }}
            transition={{
                duration: reduceMotion ? 0.2 : 0.65,
                ease: easeOut,
            }}
        >
            {children}
        </motion.div>
    );
}

export function MotionStagger({ children, className, ...props }: MotionProps) {
    const reduceMotion = useReducedMotion();

    return (
        <motion.div
            className={className}
            data-motion="stagger"
            {...props}
            variants={reduceMotion ? undefined : containerVariants}
            initial={reduceMotion ? false : 'hidden'}
            whileInView={reduceMotion ? undefined : 'visible'}
            viewport={{ once: true, amount: 0.16 }}
        >
            {children}
        </motion.div>
    );
}

export function MotionStaggerItem({
    children,
    className,
    ...props
}: MotionProps) {
    return (
        <motion.div className={className} variants={itemVariants} {...props}>
            {children}
        </motion.div>
    );
}
