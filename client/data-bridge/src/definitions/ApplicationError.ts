import DataType from '@/datamodel/DataType';
import { SnakType } from '@/datamodel/Snak';
import { MissingPermissionsError } from '@/definitions/data-access/BridgePermissionsRepository';

export enum ErrorTypes {
	INITIALIZATION_ERROR = 'INITIALIZATION_ERROR',
	APPLICATION_LOGIC_ERROR = 'APPLICATION_LOGIC_ERROR',
	INVALID_ENTITY_STATE_ERROR = 'INVALID_ENTITY_STATE_ERROR',
	UNSUPPORTED_AMBIGUOUS_STATEMENT = 'UNSUPPORTED_AMBIGUOUS_STATEMENT',
	UNSUPPORTED_DEPRECATED_STATEMENT = 'UNSUPPORTED_DEPRECATED_STATEMENT',
	UNSUPPORTED_SNAK_TYPE = 'UNSUPPORTED_SNAK_TYPE',
	UNSUPPORTED_DATATYPE = 'UNSUPPORTED_DATATYPE',
	UNSUPPORTED_DATAVALUE_TYPE = 'UNSUPPORTED_DATAVALUE_TYPE',
	SAVING_FAILED = 'SAVING_FAILED',
}

export interface ApplicationErrorBase {
	type: string;
	info?: object;
}

interface InitializationError extends ApplicationErrorBase {
	type: ErrorTypes.INITIALIZATION_ERROR;
	info: object;
}

interface ApplicationLogicError extends ApplicationErrorBase {
	type: ErrorTypes.APPLICATION_LOGIC_ERROR;
	info: {
		stack?: string;
	};
}

interface InvalidEntityStateError extends ApplicationErrorBase {
	type: ErrorTypes.INVALID_ENTITY_STATE_ERROR
	| ErrorTypes.UNSUPPORTED_AMBIGUOUS_STATEMENT
	| ErrorTypes.UNSUPPORTED_DEPRECATED_STATEMENT
	| ErrorTypes.UNSUPPORTED_DATAVALUE_TYPE;
}

export interface UnsupportedDatatypeError extends ApplicationErrorBase {
	type: ErrorTypes.UNSUPPORTED_DATATYPE;
	info: {
		unsupportedDatatype: DataType;
	};
}

export interface UnsupportedSnakTypeError extends ApplicationErrorBase {
	type: ErrorTypes.UNSUPPORTED_SNAK_TYPE;
	info: {
		snakType: SnakType;
	};
}

interface SavingFailedError extends ApplicationErrorBase {
	type: ErrorTypes.SAVING_FAILED;
}

type ApplicationError = MissingPermissionsError
| InitializationError
| ApplicationLogicError
| InvalidEntityStateError
| UnsupportedDatatypeError
| UnsupportedSnakTypeError
| SavingFailedError;

export default ApplicationError;
